<?php
namespace Crossmedia\Fourallportal\Task;

use Crossmedia\Fourallportal\Service\FileHandler;
use Crossmedia\Fourallportal\Service\MamClient;
use Crossmedia\Fourallportal\Task\EventHandlerState;
use Crossmedia\Fourallportal\Utilities\Path;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * This Scheduler task checks if ther are any events waiting in the event_queue
 * and processes them.
 */
class EventQueueHandler extends AbstractTask
{

    /**
     * @var \Crossmedia\Fourallportal\Service\MamClient
     */
    protected $client;

    /**
     * @var \TYPO3\CMS\Core\DataHandling\DataHandler
     */
    protected $dataHandler;

    /**
     * @var \Crossmedia\Fourallportal\Task\EventHandlerState
     */
    protected $state;

    /**
     * @var \Crossmedia\Fourallportal\Service\Configuration
     * @inject
     */
    protected $configuration;

    /**
     * @var \TYPO3\CMS\Core\Resource\ResourceStorage
     */
    protected $resourceStorage;

    /**
     * @var \TYPO3\CMS\Core\Resource\ResourceFactory
     */
    protected $resourceFactory;

    /**
     * @var string
     */
    protected $items = 10;

    /**
     * @var integer
     */
    protected $reclaimTime = 360;

    /**
     * @var \Crossmedia\Fourallportal\Service\Logger
     */
    protected $logger;

    /**
     * @param  \Crossmedia\Fourallportal\Service\MamClient $client
     * @return void
     */
    public function injectClient(\Crossmedia\Fourallportal\Service\MamClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param  \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     * @return void
     */
    public function injectDataHandler(\TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler)
    {
        $this->dataHandler = $dataHandler;
    }

    /**
     * @param  \Crossmedia\Fourallportal\Task\EventHandlerState $state
     * @return void
     */
    public function injectState(\Crossmedia\Fourallportal\Task\EventHandlerState $state)
    {
        $this->state = $state;
    }

    /**
     * @param  \Crossmedia\Fourallportal\Service\Configuration $configuration
     * @return void
     */
    public function injectConfiguration(\Crossmedia\Fourallportal\Service\Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @param \TYPO3\CMS\Core\Resource\ResourceStorage $resourceStorage
     * @return void
     */
    public function injectResourceStorage(\TYPO3\CMS\Core\Resource\ResourceStorage $resourceStorage)
    {
        $this->resourceStorage = $resourceStorage;
    }

    /**
     * @param \TYPO3\CMS\Core\Resource\ResourceFactory $resourceFactory
     * @return void
     */
    public function injectResourceFactory(\TYPO3\CMS\Core\Resource\ResourceFactory $resourceFactory)
    {
        $this->resourceFactory = $resourceFactory;
    }

    /**
     * This Scheduler task checks if ther are any events waiting in the event_queue
     * and processes them.
     */
    public function execute()
    {
        $this->initialize();
        $this->logger = new \Crossmedia\Fourallportal\Service\Logger();

        $counter = 0;
        $start = time();
        // $this->items = 10;
        while ($counter < $this->items) {
            $event = $this->claimEventFromQueue();

            if ($event === null) {
                // nothing left to do
                $this->client->logout();

                return true;
            }
            $counter++;

            #echo $event['object_id'] . ' ' . $event['event_type'] . ':' . $event['target'] . chr(10);
            $this->logger->debug($event['object_id'] . ' ' . $event['event_type'] . ':' . $event['target']);
            $success = $this->processEvent($event);

            if ($success === true) {
                $this->finnishEvent($event);
            } else {
                $this->rescheduleEvent($event);
                $this->logger->warning('rescheduling event', $event);
                // echo $this->reason . chr(10);
            }
            unset($event);
        }
        $this->addLog($start, time(), $counter);

        $this->client->logout();

        return true;
    }

    /**
     * We need to inject by ourself, because the automatic dependency injection
     * doesn't seem to work for Scheduler Tasks.
     *
     * @return void
     */
    public function initialize()
    {
        $objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
        if ($this->client === null) {
            $this->injectClient($objectManager->get('Crossmedia\Fourallportal\Service\MamClient'));
        }
        if ($this->dataHandler === null) {
            $this->injectDataHandler($objectManager->get('TYPO3\CMS\Core\DataHandling\DataHandler'));
        }
        if ($this->state === null) {
            $this->injectState($objectManager->get('Crossmedia\Fourallportal\Task\EventHandlerState'));
        }
        if ($this->configuration === null) {
            $this->injectConfiguration($objectManager->get('Crossmedia\Fourallportal\Service\Configuration'));
        }
        if ($this->resourceStorage === null) {
            $storageRepository = $objectManager->get('TYPO3\CMS\Core\Resource\StorageRepository');
            $this->resourceStorage = current($storageRepository->findByStorageType('MAM'));

            if (!is_object($this->resourceStorage)) {
                throw new \Exception('Unable to load MAM File Storage');
            }
        }

        if ($this->resourceFactory === null) {
            $this->injectResourceFactory(ResourceFactory::getInstance());
        }

        chdir(PATH_site);
    }

    /**
     * process an event
     *
     * @param  array $event
     * @return void
     */
    public function processEvent($event)
    {
        switch ($event['event_type']) {
            case 'create': // create
                return $this->processCreateEvent($event);

            case 'update': // update
                return $this->processUpdateEvent($event);
                break;

            case 'delete': // delete
                return $this->processDeleteEvent($event);
                break;

            default:
                // todo: wtf => exception
                break;
        }
    }

    /**
     * process a delete event
     *
     * @param  array $event
     * @return void
     */
    public function processDeleteEvent($event)
    {
        if ($event['target'] == 'metadata' || $event['target'] == 'both') {
            $this->logger->debug('deleting asset metadata', $event);
            $this->deleteAsset($event['object_id']);
        }

        if ($event['target'] == 'file' || $event['target'] == 'both') {
            $this->logger->debug('deleting asset file', $event);
            $this->deleteAsset($event['object_id']);
        }

        return true;
    }

    /**
     * process a update event
     *
     * @param  array $event
     * @return void
     */
    public function processCreateEvent($event)
    {
        $beans = $this->client->getBeans($event['object_id']);

        if ($beans === null) {
            return true;
        }

        $bean = current($beans);

        $result = true;

        if ($bean['type'] == 'folder') {
            $this->logger->debug('creating folder', $bean);
            $this->createFolder($bean['properties']['data_shellpath']);
            unset($bean, $beans);

            return true;
        }

        if ($event['target'] == 'file' || $event['target'] == 'both') {
            $derivateSuffix = $this->client->saveDerivate(
                Path::join($bean['properties']['data_shellpath'], $bean['properties']['data_name']),
                $event['object_id']
            );
            if ($derivateSuffix == false) {
                return false;
            }

            $result = $this->createAsset(
                $bean['properties']['data_name'],
                $bean['properties']['data_shellpath'],
                $event['object_id'],
                $bean['properties'],
                $derivateSuffix
            );
            $this->logger->debug(
                'sucessfully fetched file',
                array(
                    'bean' => $bean,
                    'event' => $event,
                )
            );

            // call hook after creating a file
            $this->callHook('fileCreated', array(
                'path' => Path::join($bean['properties']['data_shellpath'], $bean['properties']['data_name']),
            ));
        }

        if ($event['target'] == 'metadata' || $event['target'] == 'both') {
            $result = $this->updateAsset(
                $bean['properties']['data_name'],
                $bean['properties']['data_shellpath'],
                $event['object_id'],
                $bean['properties']
            );
            $this->logger->debug(
                'added metadata for file',
                array(
                    'bean' => $bean,
                    'event' => $event,
                )
            );
        }
        unset($bean, $beans);

        return $result;
    }

    /**
     * process a update event
     *
     * @param  array $event
     * @return void
     */
    public function processUpdateEvent($event)
    {
        $beans = $this->client->getBeans($event['object_id']);
        if ($beans === null) {
            return true;
        }
        $bean = current($beans);

        $result = true;

        if ($event['target'] == 'file' || $event['target'] == 'both') {
            $fileObject = $this->getFileObject($event['object_id']);

            $derivateSuffix = $this->client->saveDerivate(
                Path::join($bean['properties']['data_shellpath'], $bean['properties']['data_name']),
                $event['object_id']
            );
            if ($derivateSuffix == false) {
                $this->logger->warning(
                    'failed to identify derivate suffix',
                    array(
                        'bean' => $bean,
                        'event' => $event,
                    )
                );

                return false;
            }

            if ($fileObject === null) {
                $result = $this->createAsset(
                    $bean['properties']['data_name'],
                    $bean['properties']['data_shellpath'],
                    $event['object_id'],
                    $bean['properties']
                );
            }

            $this->logger->debug(
                'sucessfully fetched file',
                array(
                    'bean' => $bean,
                    'event' => $event,
                )
            );

            // call hook after creating a file
            $this->callHook('fileUpdated', array(
                'fileObject' => $fileObject,
            ));
        }

        if ($event['target'] == 'metadata' || $event['target'] == 'both') {
            $result = $this->updateAsset(
                $bean['properties']['data_name'],
                $bean['properties']['data_shellpath'],
                $event['object_id'],
                $bean['properties']
            );
            $this->logger->debug(
                'added metadata for file',
                array(
                    'bean' => $bean,
                    'event' => $event,
                )
            );
        }
        unset($bean, $beans);

        return $result;
    }

    /**
     * check if the configuration has changed since the last time the task has
     * run
     *
     * @return boolean
     */
    public function hasConfigurationChanged()
    {
        return $this->state->getConfigHash() !== $this->client->getConfigHash();
    }

    /**
     * create the asset in the sys_file database through the fal api.
     * This needs the file to already exist on the filesystem.
     *
     * @param  string $filename
     * @param  string $filepath
     * @param  string $mamId
     * @param  string $metadata
     * @return void
     */
    public function createAsset($filename, $filepath, $mamId, $metadata, $derivateSuffix = '')
    {
        if (strlen($derivateSuffix) > 0 && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== $derivateSuffix) {
            $filename .= '.' . $derivateSuffix;
        }

        $path = str_replace($this->configuration->base_path, '', Path::join($filepath, $filename));

        if (false == $this->fileExists(Path::join($filepath, $filename))) {
            $this->reason = 'no file has been downloaded';
            $this->logger->error('the file was not downloaded!',
                array(
                    'filepath' => Path::join($filepath, $filename),
                    'metadata' => $metadata,
                    'object_id' => $mamId,
                    'derivateSuffix' => $derivateSuffix,
                )
            );

            return false;
        }

        // if (isset($metadata['data_size']) && filesize(Path::join($filepath, $filename)) < $metadata['data_size']) {
        // 	$this->reason = 'something went wrong, the downloaded file is smaller than expected: expected ' . $metadata['data_size'] . '+ received: ' . filesize(Path::join($filepath, $filename)) . ' missing: ' . ($metadata['data_size'] - filesize(Path::join($filepath, $filename)));
        // 	// something went wrong, the downloaded file is smaller than expected -> fail!;
        // 	return FALSE;
        // }

        try {
            $fileObject = $this->resourceFactory->getObjectFromCombinedIdentifier($this->resourceStorage->getUid() . ':/' . $path);
        } catch (\Exception $e) {
            $this->reason = 'file could not be imported into fal';
            $this->logger->warning('file could not be imported into fal',
                array(
                    'filepath' => Path::join($filepath, $filename),
                    'exception' => $e->getMessage(),
                )
            );

            return false;
        }
        $fileObject->_getMetaData();

        $GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_file', 'uid = ' . $fileObject->getUid(), array(
            'tx_falmam_id' => $mamId,
            'tx_falmam_derivate_suffix' => $derivateSuffix,
            'size' => filesize(Path::join($filepath, $filename)),
        ));

        $data = $this->mapMetadata($metadata);
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_file_metadata', 'file=' . $fileObject->getUid(), $data);

        // call hook after creating an asset
        $this->callHook('assetCreated', array(
            'path' => $path,
            'fileObject' => $fileObject,
        ));

        unset($fileObject, $path, $data);

        return true;
    }

    /**
     * update the asset in the sys_file database through the fal api.
     *
     * @param  string $filename
     * @param  string $filepath
     * @param  string $mamId
     * @param  string $metadata
     * @return void
     */
    public function updateAsset($filename, $filepath, $mamId, $metadata)
    {
        $fileObject = $this->getFileObject($mamId);

        if ($fileObject === null) {
            $this->reason = 'updateAsset: file object does not exists';
            $this->logger->warning('updateAsset: file object does not exists',
                array(
                    'filepath' => Path::join($filepath, $filename),
                    'object_id' => $mamId,
                    'metadata' => $metadata,
                )
            );

            return false;
        }

        $derivateSuffix = $this->getDerivateExtension($mamId);

        if (strlen($derivateSuffix) > 0 && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== $derivateSuffix) {
            $filename .= '.' . $derivateSuffix;
        }

        $oldFilePath = Path::join($this->configuration->base_path, $fileObject->getIdentifier());
        $newFilePath = Path::join($filepath, $filename);

        if (false == $this->fileExists($newFilePath) && false === $this->fileExists($oldFilePath)) {
            // echo 'file does not exist: ' . Path::join($filepath, $filename) . chr(10);
            $this->reason = 'updateAsset: file does not exist: ' . Path::join($filepath, $filename);
            $this->logger->warning('updateAsset: file does not exist',
                array(
                    'filepath' => Path::join($filepath, $filename),
                    'object_id' => $mamId,
                    'metadata' => $metadata,
                )
            );

            return false;
        }

        if ($fileObject === null) {
            // false update event -> create!
            // return $this->createAsset($filename, $filepath, $mamId, $metadata);
            return false;
        }

        $path = str_replace($this->configuration->base_path, '', $filepath . $filename);

        if ($oldFilePath !== $newFilePath) {
            $this->moveFile($mamId, $filepath, $filename);
        }

        $GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_file', 'uid = ' . $fileObject->getUid(), array(
            'size' => filesize($newFilePath),
        ));

        $data = $this->mapMetadata($metadata);
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_file_metadata', 'file=' . $fileObject->getUid(), $data);

        // call hook after updating an asset
        $this->callHook('assetUpdated', array(
            'path' => $path,
            'fileObject' => $fileObject,
        ));

        unset($fileObject, $path, $data);

        return true;
    }

    /**
     * remove an asset rom the fal database.
     * This also removes the file from the filesystem, because fal does this
     * in one action.
     *
     * @param  string $mamId
     * @return void
     */
    public function deleteAsset($mamId)
    {
        $file = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'sys_file', 'tx_falmam_id = "' . $mamId . '"');

        if ($file === false) {
            return;
        }

        if (file_exists($this->configuration->base_path . $file['identifier'])) {
            $fileObject = $this->getFileObject($mamId);

            if ($fileObject === null) {
                return;
            }
            try {
                $this->resourceStorage->deleteFile($fileObject);
            } catch (\Exception $e) {
                $this->logging->captureException($e);
            }
        } else {
            $GLOBALS['TYPO3_DB']->exec_DELETEquery('sys_file', 'tx_falmam_id = "' . $mamId . '"');
        }

        // call hook after deleting an asset
        $this->callHook('assetDeleted', array(
            'fileObject' => $fileObject,
        ));
    }

    /**
     * claims a pending event from the even_queue table and sets the status of
     * the claimed event to "CLAIMED"
     *
     * @return array
     */
    public function claimEventFromQueue()
    {
        $rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            '*',
            'tx_falmam_event_queue',
            '(status = "NEW" AND skipuntil < ' . time() . ') OR (status = "CLAIMED" AND tstamp < ' . (time() - $this->reclaimTime) . ')',
            '',
            'skipuntil ASC, event_id',
            '5',
            'object_id'
        );
        if (count($rows) > 0) {
            $event = current($rows);
            unset($rows);

            $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                'tx_falmam_event_queue',
                'uid=' . $event['uid'],
                array(
                    'tstamp' => time(),
                    'status' => 'CLAIMED',
                )
            );

            $event['start'] = microtime(true);

            return $event;
        }
    }

    /**
     * called after finishing a event to set the status to "DONE" and save the
     * runtime.
     *
     * @param  array $event
     * @return void
     */
    public function finnishEvent($event)
    {
        if (isset($event['start'])) {
            $event['runtime'] = number_format((microtime(true) - $event['start']) * 1000, 2);
        }

        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            'tx_falmam_event_queue',
            'uid=' . $event['uid'],
            array(
                'status' => 'DONE',
                'runtime' => $event['runtime'],
                'skipuntil' => null,
            )
        );
    }

    /**
     * sets a back to the status "NEW" and adds a "skipuntil" timestamp to
     * delay the next execution by 1 second. This mostly happens when the
     * metadata event occurs before the file itself was saved to the filesystem.
     *
     * @param  array $event
     * @return void
     */
    public function rescheduleEvent($event)
    {
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            'tx_falmam_event_queue',
            'uid=' . $event['uid'],
            array(
                'tstamp' => time(),
                'status' => 'NEW',
                'skipuntil' => time() + 30,
            )
        );
    }

    /**
     * adds a log entry to the tx_falmam_log table
     *
     * @param integer $start
     * @param integer $stop
     * @param integer $count
     */
    public function addLog($start, $stop, $count)
    {
        $data = array();
        $data['tx_falmam_log']['NEW'] = array(
            'pid' => $this->configuration->storage_pid,
            'connector_name' => $this->configuration->connector_name,
            'start_time' => $start,
            'end_time' => $stop,
            'event_count' => $count,
            'runtime' => $stop - $start,
        );

        $this->dataHandler->start($data, array());
        $result = $this->dataHandler->process_datamap();
    }

    /**
     * fetches a fal file object from the ResourceFactory based on the provided
     * mamId
     *
     * @param  string $mamId
     * @return File
     */
    public function getFileObject($mamId)
    {
        $row = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
            '*',
            'sys_file',
            'tx_falmam_id = "' . $mamId . '"'
        );

        if (!is_array($row)) {
            // todo: exception!
            return null;
        }

        return $this->resourceFactory->getFileObject($row['uid'], $row);
    }

    /**
     * fetches a fal file object from the ResourceFactory based on the provided
     * mamId
     *
     * @param  string $mamId
     * @return void
     */
    public function getDerivateExtension($mamId)
    {
        $row = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
            '*',
            'sys_file',
            'tx_falmam_id = "' . $mamId . '"'
        );

        if (!is_array($row)) {
            // todo: exception!
            return null;
        }

        return $row['tx_falmam_derivate_suffix'];
    }

    /**
     * maps mam metadata based on the mapping configuration to the fal metadata
     * fields
     *
     * @param  array $metadata
     * @return array
     */
    public function mapMetadata($metadata)
    {
        $data = array();
        foreach ($this->configuration->mapping as $mamField => $mapping) {
            if (isset($metadata[$mamField]) && strlen($mapping['fal_field']) > 0) {
                $value = $metadata[$mamField];

                if (is_array($value)) {
                    foreach ($value as $key => $subValue) {
                        if (isset($mapping['value_map'][$subValue])) {
                            $value[$key] = $mapping['value_map'][$subValue];
                        }
                    }
                    $value = implode(',', $value);
                } else {
                    if (isset($mapping['value_map'][$value])) {
                        $value = $mapping['value_map'][$value];
                    }
                }

                $data[$mapping['fal_field']] = $value;
            }
        }

        // call hook after deleting an asset
        $this->callHook('mapMetadata', array(
            'data' => &$data,
            'metadata' => $metadata,
        ));

        return $data;
    }

    /**
     * normalizes a mam path to the current format
     *
     * @param  string $path
     * @return string
     */
    public function normalizePath($path)
    {
        if (strlen($this->configuration->mam_shell_path) > 0) {
            $path = str_replace($this->configuration->mam_shell_path, '', $path);
        }
        $path = ltrim($path, '/\\');

        return $path;
    }

    /**
     * move a file to its target location
     *
     * @param string $mamId
     * @param string $filepath
     * @param string $filename
     * @return void
     */
    public function moveFile($mamId, $filepath, $filename)
    {
        $fileObject = $this->getFileObject($mamId);
        if ($fileObject !== null) {
            $oldFilePath = rtrim($this->configuration->base_path, '/') . $fileObject->getIdentifier();
            $newFilePath = $filepath . $filename;
            $storagePath = str_replace($this->configuration->base_path, '', $filepath);

            if (!$this->fileExists($oldFilePath)) {
                return;
            }

            if ($oldFilePath !== $newFilePath) {
                if (!is_dir($filepath)) {
                    mkdir($filepath, 0777, true);
                }
                try {
                    $folder = $this->resourceFactory->getObjectFromCombinedIdentifier($this->resourceStorage->getUid() . ':/' . $storagePath);
                } catch (\Exception $e) {
                    return;
                }
                $this->resourceStorage->moveFile($fileObject, $folder, $filename);

                $this->cleanupEmptyFoldersInRootline(dirname($oldFilePath));
            }
        }
    }

    /**
     * checks for and deletes any empty folders in a given path
     *
     * @param  string $path
     * @return void
     */
    public function cleanupEmptyFoldersInRootline($path)
    {
        $absolutePath = realpath($path);
        if (substr($path, 0, 1) === '/' || strlen($path) < 1) {
            // something fishy! abort!
            return;
        }
        $files = array_diff(scandir($absolutePath), array('.', '..'));

        if (count($files) === 0) {
            rmdir($absolutePath);
            $this->cleanupEmptyFoldersInRootline(dirname($path));
        }
    }

    /**
     * create a folder on the filesystem
     *
     * @param  string $path
     * @return void
     */
    public function createFolder($path)
    {
        $path = PATH_site . $this->normalizePath($path);
        mkdir(dirname($path), 0777, true);
    }

    /**
     * check if a file exists on the filesystem
     *
     * @param  string $path
     * @return boolean
     */
    public function fileExists($path)
    {
        return file_exists($path);
    }

    /**
     * call a hook with a given parameter
     *
     * @param string $hookIdentifier
     * @param array $params
     * @return void
     */
    public function callHook($hookIdentifier, $params)
    {
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['fal_mam']['EventQueueHandler'])
            && is_array($GLOBALS['TYPO3_CONF_VARS']['EXT']['fal_mam']['EventQueueHandler'][$hookIdentifier])
        ) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXT']['fal_mam']['EventQueueHandler'][$hookIdentifier] as $reference) {
                \TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction($reference, $params, $this);
            }
        }
    }

    /**
     * @param integer $items
     */
    public function setItems($items)
    {
        $this->items = $items;
    }

    /**
     * @return integer
     */
    public function getItems()
    {
        return $this->items;
    }
}
