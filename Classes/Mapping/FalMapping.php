<?php
namespace Crossmedia\Fourallportal\Mapping;

use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Service\ApiClient;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

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
                $repository->remove($object);
                break;
            case 'update':
                if (!$object) {
                    // push back event.

                    return;
                }
            case 'create':
                $object = $this->downloadFileAndGetFileObject($objectId, $data, $event);
                $this->mapPropertiesFromDataToObject($data, $object);
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
     * @return AbstractEntity
     */
    protected function mapPropertiesFromDataToObject(array $data, $object)
    {
        parent::mapPropertiesFromDataToObject($data, $object);
        $metadata = [];
        $map = MappingRegister::resolvePropertyMapForMapper(static::class);

        foreach ($data['result'][0]['properties'] as $propertyName => $propertyValue) {
            if (isset($map[$propertyName])) {
                $targetPropertyName = $map[$propertyName];
                if (substr($targetPropertyName, 0, 9) === 'metadata.') {
                    $metadata[substr($targetPropertyName, 9)] = $propertyValue;
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
        $targetFilename = $data['result'][0]['properties']['data_name'];
        $tempPathAndFilename = GeneralUtility::tempnam('mamfal', $targetFilename);

        $trimShellPath = $event->getModule()->getShellPath();
        $targetFolder = substr($data['result'][0]['properties']['data_shellpath'], strlen($trimShellPath));

        $client = $this->getClientByServer($event->getModule()->getServer());
        $storage = (new StorageRepository())->findByUid($event->getModule()->getFalStorage());
        try {
            $folder = $storage->getFolder($targetFolder);
        } catch (FolderDoesNotExistException $error) {
            $folder = $storage->createFolder($targetFolder);
        }

        $client->saveDerivate($tempPathAndFilename, $event->getObjectId());
        $contents = file_get_contents($tempPathAndFilename);

        try {
            $file = $folder->createFile($targetFilename);
        } catch (ExistingTargetFileNameException $error) {
            $file = reset($this->getObjectRepository()->searchByName($folder, $targetFilename));
        }

        if (!$file) {
            throw new \RuntimeException('Unable to either create or re-use existing file: ' . $targetFolder . $targetFilename);
        }

        $file->setContents($contents);

        $queryBuilder = (new ConnectionPool())->getConnectionForTable('sys_file')->createQueryBuilder();
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
        foreach($beans['result'] as $result) {
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
        $temporaryFile = GeneralUtility::tempnam('derivative_');
        $client->saveDerivate($temporaryFile, $ids[0]);
        if (!file_exists($temporaryFile)) {
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
}
