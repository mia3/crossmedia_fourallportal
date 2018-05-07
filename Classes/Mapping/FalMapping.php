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
use TYPO3\CMS\Core\Resource\Driver\AbstractHierarchicalFilesystemDriver;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
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
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
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
     */
    public function import(array $data, Event $event)
    {
        $repository = $this->getObjectRepository();
        $objectId = $event->getObjectId();
        $object = null;

        // We have to do things the hard way, unfortunately. Because someone didn't implement a real Repository but declared the class a Repository anyway. Sigh.
        $queryBuilder = (new ConnectionPool())->getConnectionForTable('sys_file')->createQueryBuilder();
        $query = $queryBuilder->select('uid')->from('sys_file')->where($queryBuilder->expr()->eq('remote_id', $queryBuilder->quote($objectId)))->setMaxResults(1);
        $record = $query->execute()->fetch();
        if ($record) {
            $object = $repository->findByUid($record['uid']);
        }

        switch ($event->getEventType()) {
            case 'delete':
                if (!$object) {
                    // push back event.
                    return;
                }
                /** @var File $object */
                $object->delete();
                $repository->remove($object);
                break;
            case 'update':
            case 'create':
                $object = $this->downloadFileAndGetFileObject($objectId, $data, $event);
                $this->mapPropertiesFromDataToObject($data, $object, $event->getModule());
                break;
            default:
                throw new \RuntimeException('Unknown event type: ' . $event->getEventType());
        }

        GeneralUtility::makeInstance(ObjectManager::class)->get(PersistenceManager::class)->persistAll();

        if ($object) {
            $this->processRelationships($object, $data, $event);
        }
    }

    /**
     * @param array $data
     * @param \TYPO3\CMS\Extbase\DomainObject\AbstractEntity $object
     * @param Module $module
     * @param DimensionMapping|null $dimensionMapping
     * @return AbstractEntity
     */
    protected function mapPropertiesFromDataToObject(array $data, $object, Module $module, DimensionMapping $dimensionMapping = null)
    {
        parent::mapPropertiesFromDataToObject($data, $object, $module, $dimensionMapping);
        $metadata = [];
        $map = MappingRegister::resolvePropertyMapForMapper(static::class);
        $fieldValueReader = new ResponseDataFieldValueReader();

        foreach ($data['result'][0]['properties'] as $propertyName => $propertyValue) {
            if (isset($map[$propertyName])) {
                $targetPropertyName = $map[$propertyName];
                if (substr($targetPropertyName, 0, 9) === 'metadata.') {
                    $metadata[substr($targetPropertyName, 9)] = $fieldValueReader->readResponseDataField($data['result'], 'value');
                }
            }
        }

        if (!empty($metadata)) {
            $metadataRepository = $this->getMetaDataRepository();
            $metadataRepository->update($object->getUid(), $metadata);
        }
        return $object;
    }

    /**
     * @param string $objectId
     * @param array $data
     * @param Event $event
     * @return File
     */
    protected function downloadFileAndGetFileObject($objectId, array $data, Event $event)
    {
        $fieldValueReader = new ResponseDataFieldValueReader();
        $originalFileName = $fieldValueReader->readResponseDataField($data['result'], 'data_name');
        $targetFilename = $this->sanitizeFileName($originalFileName);
        $tempPathAndFilename = GeneralUtility::tempnam('mamfal', $targetFilename);

        $trimShellPath = $event->getModule()->getShellPath();
        $targetFolder = trim(substr($fieldValueReader->readResponseDataField($data['result'], 'data_shellpath'), strlen($trimShellPath)), '/');
        $targetFolder = implode('/', array_map([$this, 'sanitizeFileName'], explode('/', $targetFolder))) . '/';

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

        $finalFileName = $fieldValueReader->readResponseDataField($data['result'], 'bm_typo3_title');
        $finalFileExtension = $fieldValueReader->readResponseDataField($data['result'], 'bm_derivatsformat');
        if (!empty($finalFileName) && !empty($finalFileExtension)) {
            $targetFilename =  $finalFileName . '.' . $finalFileExtension;
        }

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
                new \DateTime($fieldValueReader->readResponseDataField($data['result'], 'mod_time_img')
                    ?? $fieldValueReader->readResponseDataField($data['result'], 'mod_time')
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
        $events = $client->getEvents($module->getConnectorName(), 0);
        $ids = [];
        foreach($events as $event) {
            if ($event['event_type'] === 0) {
                continue;
            }
            $ids[] = $event['object_id'];
            if (count($ids) == 3) {
                break;
            }
        }
        $messages = [];
        $beans = $client->getBeans($ids, $module->getConnectorName());
        $paths = [];
        $status['description'] .= '
            <h3>FalMapping</h3>
        ';
        if (!isset($beans['result'])) {
            $messages['no_beans'] = '<p><strong class="text-danger">The connector did not return any beans when queried! Response: ' . var_export($beans, true) . '</strong></p>';
        }
        $files = $beans['result'] ?? [];

        foreach($files as $result) {
            if (!isset($result['properties']['data_name'])) {
                $status['class'] = 'danger';
                $messages['data_name'] = '<p><strong class="text-danger">Connector does not provide required "data_name" property</strong></p>';
            }
            if (!isset($result['properties']['data_shellpath'])) {
                $messages['data_shellpath'] = '<p><strong class="text-danger">Connector does not provide required "data_shellpath" property</strong></p>';
                $status['class'] = 'danger';
            }
            $paths[] = $result['properties']['data_shellpath'] . $result['properties']['data_name'];
       }
        if (empty($module->getShellPath())) {
            $status['class'] = 'danger';
            $messages['shellpath_missing'] = '
                <p>
                    <strong class="text-danger">Missing ShellPath in ModuleConfig</strong><br />
                </p>
            ';
        } elseif (strpos($paths[0], $module->getShellPath()) !== 0) {
            $status['class'] = 'danger';
            $messages['shellpath_mismatch'] = sprintf('
                <p>
                    <strong class="text-danger">Shell path of module does not match base path of returned file. Expected base path "%s" but path of returned file was "%s"</strong><br />
                </p>
            ', $module->getShellPath(), $paths[0]);
        } else {
            $messages['shellpath_good'] = '
                <p>
                    <strong class="text-success">Shell path checks successful: path is configured and matches base path of probed File result.</strong><br />
                </p>
            ';
        }
        if (count($files)) {
            $temporaryFile = $client->saveDerivate(GeneralUtility::tempnam('derivative_'), $ids[0]);
            if (!file_exists($temporaryFile) || !filesize($temporaryFile)) {
                $status['class'] = 'danger';
                $messages['derivative_download_failed'] = sprintf('
                    <p>
                        <strong class="text-danger">ApiClient was unable to download derivative with ID %s. Errors have been logged or are displayed above.</strong><br />
                    </p>
                ', $ids[0]);
            } else {
                $receivedBytes = filesize($temporaryFile);
                $messages['derivative_download_success'] = sprintf('
                    <p>
                        <strong class="text-success">Derivative was downloaded from API. Received %d bytes.</strong><br />
                    </p>
                ', $receivedBytes);
            }
            $messages[] = '<p>
                    <strong>Paths of the 3 first Files:</strong><br />
                    ' . implode('<br />', $paths) . '
                </p>';
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
