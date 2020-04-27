<?php
namespace Crossmedia\Fourallportal\Mapping;

use Crossmedia\Fourallportal\Domain\Model\DimensionMapping;
use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Service\ApiClient;
use Crossmedia\Fourallportal\ValueReader\ResponseDataFieldValueReader;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileNameException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Reflection\Exception\PropertyNotAccessibleException;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

class FalMapping extends AbstractMapping
{
    /**
     * @var string
     */
    protected $repositoryClassName = FileRepository::class;

    /**
     * @return string
     */
    public function getEntityClassName()
    {
        return FileReference::class;
    }

    /**
     * @param array $data
     * @param Event $event
     * @return bool
     */
    public function import(array $data, Event $event)
    {
        $repository = $this->getObjectRepository();
        $objectId = $event->getObjectId();
        /** @var File|null $object */
        $object = null;

        // We have to do things the hard way, unfortunately. Because someone didn't implement a real Repository but declared the class a Repository anyway. Sigh.
        $queryBuilder = (new ConnectionPool())->getConnectionForTable('sys_file')->createQueryBuilder();
        $query = $queryBuilder->select('uid')->from('sys_file')->where($queryBuilder->expr()->eq('remote_id', $queryBuilder->quote($objectId)))->setMaxResults(1);
        $record = $query->execute()->fetch();
        if ($record) {
            $object = $repository->findByUid($record['uid']);
        }

        $deferAfterProcessing = false;

        switch ($event->getEventType()) {
            case 'delete':
                if (!$object && !$record) {
                    // Object is already deleted, return false meaning no deferral after processing.
                    return false;
                }

                // Do reference checking because references to sys_file are extremely prone to throwing exceptions if
                // a file is suddenly removed. The repository does not complain about such cases so we check it here.
                // Failures (as in: references that block deletion) cause a DeferralException which cases the event to
                // be continuously retried until it either fails because of TTL, or references are removed.
                if ($record) {
                    $this->performSanityCheckBeforeDeletion($record);
                }

                if ($object && !$object->isMissing() && !$object->isDeleted()) {
                    $object->delete();
                    $repository->remove($object);
                }

                break;
            case 'update':
            case 'create':
                $object = $this->downloadFileAndGetFileObject($objectId, $data, $event);
                $deferAfterProcessing = $this->mapPropertiesFromDataToObject($data, $object, $event->getModule());
                break;
            default:
                throw new \RuntimeException('Unknown event type: ' . $event->getEventType());
        }

        GeneralUtility::makeInstance(ObjectManager::class)->get(PersistenceManager::class)->persistAll();

        if ($object) {
            $this->processRelationships($object, $data, $event);
        }

        return $deferAfterProcessing;
    }

    /**
     * @param File $file
     * @param array $record
     * @param Module $module
     * @throws DeferralException
     */
    protected function performSanityCheckBeforeDeletion(array $record)
    {
        $queryBuilder = (new ConnectionPool())->getConnectionForTable('sys_file_reference')->createQueryBuilder();
        $query = $queryBuilder->select('*')
            ->from('sys_file_reference')
            ->where('uid_local = :fileUid AND deleted = 0')
            ->setParameter('fileUid', $record['uid']);
        $existingReferences = $query->execute()->fetchAll();

        list ($referringRecords, $referringObjects) = $this->collectTablesAndRecordsWithRelationsToFileReferences($existingReferences);

        if (!empty($referringRecords)) {
            // Records, possibly also PIM-synced objects, are still referring to one or more references to this file.
            // Cowardly refuse to delete it, but defer the event so it will be retried.
            $exceptionMessage = 'The following records refer to the file and prevent deleting it: ' . implode(', ', $referringRecords) . '.';
            if (!empty($referringObjects)) {
                $exceptionMessage .= ' The following PIM objects still refer to the image and also prevent deletion: ' . implode(', ', $referringObjects) . '.';
            }
            throw new DeferralException($exceptionMessage, 1528123767);
        }
    }

    protected function collectTablesAndRecordsWithRelationsToFileReferences(array $existingReferences): array
    {
        $referenceUids = array_column($existingReferences, 'uid');
        $originalUids = array_column($existingReferences, 'uid_local');
        $targetTables = [];
        $references = [];
        $referringObjects = [];
        foreach ($GLOBALS['TCA'] as $table => $config) {
            if ($table === 'sys_file_metadata' || $table === 'sys_file_reference' || $table === 'sys_file') {
                // We will silently ignore the file related tables themselves
                continue;
            }
            $collectedFieldNames = [];
            foreach ($config['columns'] as $columnName => $columnConfiguration) {
                if ($columnConfiguration['config']['type'] !== 'select' && $columnConfiguration['config']['type'] !== 'group' && $columnConfiguration['config']['type'] !== 'inline') {
                    continue;
                }
                if ($columnConfiguration['config']['type'] === 'group' && strpos($columnConfiguration['config']['allowed'], 'sys_file') === false) {
                    continue;
                }
                $foreignTable = $columnConfiguration['config']['foreign_table'] ?? $columnConfiguration['config']['allowed'] ?? '';
                if ($foreignTable === 'sys_file_reference' || $foreignTable === 'sys_file' || strpos($foreignTable, 'sys_file') !== false) {
                    $collectedFieldNames[] = $columnName;
                    $targetTables[$table][$columnName] = $foreignTable;
                }
            }

            $selectColumns = $collectedFieldNames;
            $selectColumns[] = 'uid';
            if (isset($config['remote_id'])) {
                $selectColumns[] = 'remote_id';
            }

            if (empty($collectedFieldNames)) {
                continue;
            }

            $queryBuilder = (new ConnectionPool())->getConnectionForTable($table)->createQueryBuilder();
            foreach ($queryBuilder->select(...$selectColumns)->from($table)->execute()->fetchAll() as $record) {
                foreach ($collectedFieldNames as $fieldName) {
                    $referredValues = [];
                    if ($config['columns'][$fieldName]['config']['type'] === 'select' || $config['columns'][$fieldName]['config']['type'] === 'group') {
                        if (!isset($config['columns'][$fieldName]['config']['foreign_field'])) {
                            $referredValues = array_filter(GeneralUtility::trimExplode(',', $record[$fieldName]));
                        }
                    }
                    foreach ($existingReferences as $existingReference) {
                        if (
                            $table === $existingReference['tablenames']
                            && $existingReference['fieldname'] === $fieldName
                            && (int)$existingReference['uid_foreign'] === (int)$record['uid']
                        ) {
                            $referredValues[] = $existingReference['uid'];
                        }
                    }
                    foreach ($referredValues as $referredValue) {
                        if (in_array((int)$referredValue, $targetTables[$table][$fieldName] === 'sys_file' ? $originalUids : $referenceUids)) {
                            $references[] = $table . ':' . $record['uid'] . ':' . $fieldName;
                            if (isset($record['remote_id'])) {
                                $referringObjects[] = $table . ':' . $record['remote_id'] . ':' . $fieldName;
                            }
                        }
                    }
                }
            }
        }
        return [$references, $referringObjects];
    }

    /**
     * @param array $data
     * @param \TYPO3\CMS\Extbase\DomainObject\AbstractEntity $object
     * @param Module $module
     * @param DimensionMapping|null $dimensionMapping
     * @return bool
     */
    protected function mapPropertiesFromDataToObject(array $data, $object, Module $module, DimensionMapping $dimensionMapping = null)
    {
        $deferAfterProcessing = parent::mapPropertiesFromDataToObject($data, $object, $module, $dimensionMapping);
        $metadata = [];
        $map = MappingRegister::resolvePropertyMapForMapper(static::class);
        $fieldValueReader = new ResponseDataFieldValueReader();

        foreach ($data['result'][0]['properties'] as $propertyName => $propertyValue) {
            if (isset($map[$propertyName])) {
                $targetPropertyName = $map[$propertyName];
                if (substr($targetPropertyName, 0, 9) === 'metadata.') {
                    $metadata[substr($targetPropertyName, 9)] = $fieldValueReader->readResponseDataField($data['result'], $propertyName, $dimensionMapping);
                }
            }
        }

        if (!empty($metadata)) {
            $metadataRepository = $this->getMetaDataRepository();
            $metadataRepository->update($object->getUid(), $metadata);
        }
        return $deferAfterProcessing;
    }

    /**
     * @param string $objectId
     * @param array $data
     * @param Event $event
     * @return File
     */
    protected function downloadFileAndGetFileObject($objectId, array $data, Event $event)
    {
        $dimensionMapping = $event->getModule()->getServer()->getDimensionMappings()->current();
        $fieldValueReader = new ResponseDataFieldValueReader();

        $originalFullFileName = $fieldValueReader->readResponseDataField($data['result'][0], 'name', $dimensionMapping);

        $originalFileExtension = pathinfo($originalFullFileName, PATHINFO_EXTENSION);
        $originalFileName = pathinfo($originalFullFileName, PATHINFO_FILENAME);

        try {
            $finalFileName = $fieldValueReader->readResponseDataField($data['result'][0], 'bm_typo3_title', $dimensionMapping);
        } catch (PropertyNotAccessibleException $error) {
            $finalFileName = null;
        }

        try {
            $finalFileExtension = $fieldValueReader->readResponseDataField($data['result'][0], 'bm_derivatsformat', $dimensionMapping);
        } catch (PropertyNotAccessibleException $error) {
            $finalFileExtension = null;
        }

        $targetFilename = ($finalFileName ?? $originalFileName) . '.' . ($finalFileExtension ?? $originalFileExtension);
        $targetFilename = $this->sanitizeFileName($targetFilename);

        $tempPathAndFilename = GeneralUtility::tempnam('mamfal', $targetFilename);

        $targetFolder = trim($fieldValueReader->readResponseDataField($data['result'][0], 'parent_path', $dimensionMapping) . '/');
        $targetFolder = implode('/', array_map([$this, 'sanitizeFileName'], explode('/', trim($targetFolder, '/')))) . '/';

        $client = $this->getClientByServer($event->getModule()->getServer());
        $storage = (new StorageRepository())->findByUid($event->getModule()->getFalStorage());
        try {
            $folder = $storage->getFolder($targetFolder);
        } catch (FolderDoesNotExistException $error) {
            $folder = $storage->createFolder($targetFolder);
            echo 'Created folder ' . $targetFolder . PHP_EOL;
        }

        $download = !empty($targetFolder . $targetFilename);
        $file = null;

        $queryBuilder = (new ConnectionPool())->getConnectionForTable('sys_file')->createQueryBuilder();
        $query = $queryBuilder->select('*')
            ->from('sys_file')
            ->where('remote_id = :objectId')
            ->setParameter('objectId', $objectId);
        $existingFileRows = $query->execute()->fetchAll();
        if ($folder->hasFile($targetFilename)) {
            /** @var FileInterface $file */
            $file = reset($this->getObjectRepository()->searchByName($folder, $targetFilename)) ?: null;
            $remoteModificationTime = (
                new \DateTime($fieldValueReader->readResponseDataField($data['result'][0], 'mod_time_img', $dimensionMapping)
                    ?? $fieldValueReader->readResponseDataField($data['result'][0], 'mod_time', $dimensionMapping)
                )
            )->format('U');
            $download = $file && $file->getModificationTime() < $remoteModificationTime;
        }

        if ($download) {
//            echo 'Downloading: ' . $targetFolder . $targetFilename . PHP_EOL;
            try {
                $tempPathAndFilename = $client->saveDerivate($tempPathAndFilename, $event->getObjectId(), $event->getModule()->getUsageFlag());
                $contents = file_get_contents($tempPathAndFilename);
                unlink($tempPathAndFilename);
                $targetFilename = $this->sanitizeFileName(pathinfo($tempPathAndFilename, PATHINFO_BASENAME));
                if (count($existingFileRows) > 0) {
                    foreach($existingFileRows as $existingFileRow) {
                        $existingFile = $storage->getFile($existingFileRow['identifier']);
                        if (!$existingFile || $existingFileRow['name'] !== $targetFilename || !ObjectAccess::getProperty($storage, 'driver', true)->fileExists($existingFile->getIdentifier())) {
                            // File is determined to not exist, but exists in database. Remove the record, create file anew.
                            // If this is not done, various permission nonsense is raised by FAL without indication of the
                            // actual error. Any problem ranging from a missing file over file/folder permissions to user
                            // restrictions may be in effect, all of which result in the same error. We target the "file is
                            // missing" case specifically here since that's the case we are likely to encounter when renaming.
                            $queryBuilder->delete('sys_file')->where($queryBuilder->expr()->eq('uid', $existingFileRow['uid']))->execute();
                        } elseif ($existingFileRow['name'] === $targetFilename) {
                            // Note: this case reached only if file physically exists and has the same name, due to check above.
                            $file = $existingFile;
                        }
                    }
                } else {
                    $file = $folder->createFile($targetFilename);
                }
            } catch (ExistingTargetFileNameException $error) {
                $file = reset($this->getObjectRepository()->searchByName($folder, $targetFilename));
            } catch (ApiException $error) {
                throw new \RuntimeException($error->getMessage(), $error->getCode());
            }

            if (!$file) {
                $file = $folder->createFile($targetFilename);
            }

            $file->setContents($contents);
            $file->updateProperties([
                'modification_date' => $remoteModificationTime
            ]);
        } else {
            //echo 'Skipping: ' . $targetFolder . $targetFilename . PHP_EOL;
        }

        if (!$file) {
            throw new \RuntimeException('Unable to either create or re-use existing file: ' . $targetFolder . $targetFilename, 1508242161);
        }

        $query = $queryBuilder->update('sys_file', 'f')
            ->set('f.remote_id', $objectId)
            ->where($queryBuilder->expr()->eq('f.uid', $file->getUid()))
            ->setMaxResults(1);

        if (!is_int($query->execute())) {
            throw new \RuntimeException('Failed to update remote_id column of sys_file table for file with UID ' . $file->getUid());
        }

        return $file;
    }

    /**
     * @param string $fileName
     * @param string $charset
     * @return string
     * @throws InvalidFileNameException
     */
    protected function sanitizeFileName($fileName, $charset = 'utf-8')
    {
        // Handle UTF-8 characters
        if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['UTF8filesystem']) {
            // Allow ".", "-", 0-9, a-z, A-Z and everything beyond U+C0 (latin capital letter a with grave)
            $cleanFileName = preg_replace('/[' . LocalDriver::UNSAFE_FILENAME_CHARACTER_EXPRESSION . ']/u', '_', trim($fileName));
        } else {
            $fileName = $this->getCharsetConversion()->specCharsToASCII($charset, $fileName);
            // Replace unwanted characters by underscores
            $cleanFileName = preg_replace('/[' . LocalDriver::UNSAFE_FILENAME_CHARACTER_EXPRESSION . '\\xC0-\\xFF]/', '_', trim($fileName));
        }
        // Strip trailing dots and return
        $cleanFileName = rtrim($cleanFileName, '.');
        if ($cleanFileName === '') {
            throw new InvalidFileNameException(
                'File name ' . $fileName . ' is invalid.',
                1320288991
            );
        }
        $extension = pathinfo($cleanFileName, PATHINFO_EXTENSION);
        $lowercaseExtension = strtolower($extension);
        if ($lowercaseExtension === 'jpeg') {
            // Force renamed file extension as it will be processed by FAL
            $lowercaseExtension = 'jpg';
        }
        if ($extension !== $lowercaseExtension) {
            $cleanFileName = pathinfo($cleanFileName, PATHINFO_FILENAME) . '.' . $lowercaseExtension;
        }
        return $cleanFileName;
    }

    /**
     * @param Server $server
     * @return ApiClient
     */
    protected function getClientByServer(Server $server)
    {
        static $clients = [];
        $serverId = $server->getUid();
        if (isset($clients[$serverId])) {
            return $clients[$serverId];
        }
        $client = GeneralUtility::makeInstance(ObjectManager::class)->get(ApiClient::class, $server);
        $client->login();
        $clients[$serverId] = $client;
        return $client;
    }

    /**
     * @param ApiClient $client
     * @param Module $module
     * @param array $status
     * @return array
     */
    public function check(ApiClient $client, Module $module, array $status)
    {
        $status = parent::check($client, $module, $status);

        $ids = [$module->getTestObjectUuid()];
        $messages = [];

        try {
            $beans = $client->getBeans($ids, $module->getConnectorName());
        } catch (\RuntimeException $error) {
            $status['description'] = $error->getMessage();
            $status['class'] = 'danger';
            return $status;
        }

        $paths = [];
        $status['description'] .= '
            <h3>FalMapping</h3>
        ';
        if (!isset($beans['result'])) {
            $messages['no_beans'] = '<p><strong class="text-danger">The connector did not return any beans when queried! Response: ' . var_export($beans, true) . '</strong></p>';
        }
        $files = $beans['result'] ?? [];

        foreach($files as $result) {
            if (!isset($result['properties']['name']['value'])) {
                $status['class'] = 'danger';
                $messages['data_name'] = '<p><strong class="text-danger">Connector does not provide required "data_name" property</strong></p>';
            }
            $paths[] = $result['properties']['data_shellpath']['value'] . $result['properties']['name']['value'];
        }
        if (count($files)) {
            try {
                $temporaryFile = $client->saveDerivate(GeneralUtility::tempnam('derivative_'), $ids[0], $module->getUsageFlag());
                if (!file_exists($temporaryFile) || !filesize($temporaryFile)) {
                    $status['class'] = 'danger';
                    $messages['derivative_download_failed'] = sprintf('
                        <p>
                            <strong class="text-danger">ApiClient was unable to download derivative with ID %s. Errors have been logged or are displayed above.</strong><br />
                        </p>
                    ', $ids[0]);
                } else {
                    $publicTempFile = PATH_site . 'typo3temp/assets/images/' . basename($temporaryFile);
                    rename($temporaryFile, $publicTempFile);
                    $temporaryFileRelativePath = substr($publicTempFile, strlen(PATH_site) - 1);
                    $receivedBytes = filesize($publicTempFile);
                    $messages['derivative_download_success'] = sprintf(
                        '
                            <p>
                                <strong class="text-success">Derivative was downloaded from API. Received %d bytes.</strong><br />
                            </p>
                            <p>
                                <img src="%s" alt="%s" height="200" />
                            </p>
                        ',
                        $receivedBytes,
                        $temporaryFileRelativePath,
                        $temporaryFileRelativePath
                    );
                }
            } catch (\RuntimeException $error) {
                $status['class'] = 'danger';
                $messages['derivative_download_failed'] = $error->getMessage();
            }
            $messages[] = '<p>' . implode('<br />', $paths) . '</p>';
        }
        $status['description'] .= implode(chr(10), $messages);

        return $status;
    }

    /**
     * @return MetaDataRepository
     */
    protected function getMetaDataRepository()
    {
        return GeneralUtility::makeInstance(MetaDataRepository::class);
    }

    /**
     * @return CharsetConverter
     */
    protected function getCharsetConversion()
    {
        return GeneralUtility::makeInstance(CharsetConverter::class);
    }
}
