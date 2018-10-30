<?php
namespace Crossmedia\Fourallportal\Service;

use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Mapping\DeferralException;
use TYPO3\CMS\Core\Locking\Exception\LockCreateException;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Cli\Response;
use TYPO3\CMS\Extbase\Mvc\ResponseInterface;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

class EventExecutionService implements SingletonInterface
{
    /**
     * @var \Crossmedia\Fourallportal\Domain\Repository\ServerRepository
     * */
    protected $serverRepository = null;

    /**
     * @var \Crossmedia\Fourallportal\Domain\Repository\EventRepository
     * */
    protected $eventRepository = null;

    /**
     * @var \Crossmedia\Fourallportal\Domain\Repository\ModuleRepository
     * */
    protected $moduleRepository = null;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    public function injectEventRepository(\Crossmedia\Fourallportal\Domain\Repository\EventRepository $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    public function injectModuleRepository(\Crossmedia\Fourallportal\Domain\Repository\ModuleRepository $moduleRepository)
    {
        $this->moduleRepository = $moduleRepository;
    }

    public function injectServerRepository(\Crossmedia\Fourallportal\Domain\Repository\ServerRepository $serverRepository)
    {
        $this->serverRepository = $serverRepository;
    }

    public function injectObjectManager(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function __construct()
    {
        $this->response = new Response();
    }

    public function setResponse(ResponseInterface $response): void
    {
        $this->response = $response;
    }

    /**
     * Replay events
     *
     * Replays the specified number of events, optionally only
     * for the provided module named by connector or module name.
     *
     * By default the command replays only the last event.
     *
     * @param int $events
     * @param string $module
     * @param string $objectId
     */
    public function replay($events = 1, $module = null, $objectId = null)
    {
        try {
            $this->lock();
        } catch (\Exception $error) {
            $this->response->setContent('Cannot acquire lock - exiting without error' . PHP_EOL);
            $this->response->send();
            return;
        }
        foreach ($this->getActiveModuleOrModules($module) as $moduleObject) {
            $eventQuery = $this->eventRepository->createQuery();
            if (!$objectId) {
                $eventQuery->matching(
                    $eventQuery->equals('module', $moduleObject->getUid())
                );
            } else {
                $eventQuery->matching(
                    $eventQuery->logicalAnd(
                        $eventQuery->equals('module', $moduleObject->getUid()),
                        $eventQuery->equals('object_id', $objectId)
                    )
                );
            }
            $eventQuery->setLimit($events);
            $eventQuery->setOrderings(['event_id' => 'DESC']);
            foreach ($eventQuery->execute() as $event) {
                $event->setStatus('pending');
                $this->eventRepository->update($event);
                $this->objectManager->get(PersistenceManagerInterface::class)->persistAll();
                $this->processEvent($event);
            }
        }
        $this->unlock();
    }

    /**
     * Sync data
     *
     * Execute this to synchronise events from the PIM API.
     *
     * @param bool $sync Set to "1" to trigger a full sync
     * @param string $module If passed can be used to only sync one module, using the module or connector name it has in 4AP.
     * @param string $exclude Exclude a list of modules from processing (CSV string module names)
     * @param bool $force If set, forces the sync to run regardless of lock and will neither lock nor unlock the task
     */
    public function sync($sync = false, $module = null, $exclude = null, $force = false)
    {

        if (!empty($exclude)) {
            $exclude = explode(',', $exclude);
        } else {
            $exclude = [];
        }

        $activeModules = $this->getActiveModuleOrModules($module);

        $deferredEvents = [];
        foreach ($this->eventRepository->findByStatus('deferred') as $event) {
            if (in_array($event->getModule()->getModuleName(), $exclude)) {
                continue;
            }
            if (!in_array($event->getModule(), $activeModules)) {
                continue;
            }
            $deferredEvents[$event->getModule()->getModuleName()][$event->getObjectId()][] = $event;
        }
        if ($sync && !$module && !$exclude) {
            $GLOBALS['TYPO3_DB']->exec_TRUNCATEquery('tx_fourallportal_domain_model_event');
        }

        foreach ($activeModules as $module) {
            if (in_array($module->getModuleName(), $exclude)) {
                continue;
            }
            $client = $module->getServer()->getClient();
            if (empty($module->getModuleName())) {
                $connectorConfig = $client->getConnectorConfig($module->getConnectorName());
                $module->setModuleName($connectorConfig['moduleConfig']['module_name']);
            }

            /** @var Module $configuredModule */
            if ($sync && $module->getLastReceivedEventId() > 0) {
                $module->setLastReceivedEventId(0);
                $moduleEvents = $this->eventRepository->findByModule($module->getUid());
                foreach ($moduleEvents as $moduleEvent) {
                    $this->eventRepository->remove($moduleEvent);
                }
                $this->moduleRepository->update($module);
                $this->objectManager->get(PersistenceManagerInterface::class)->persistAll();
            }

            $results = $this->readAllPendingEvents($client, $module->getConnectorName(), $module->getLastReceivedEventId());
            $queuedEventsForModule = [];
            $lastEventId = 0;
            foreach ($results as $result) {
                $this->response->setContent('Receiving event ID "' . $result['id'] . '" from connector "' . $module->getConnectorName() . '"' . PHP_EOL);
                if (!$result['id']) {
                    $this->response->appendContent(var_export($result, true) . PHP_EOL);
                }
                $this->response->send();
                if (isset($queuedEventsForModule[$result['object_id']])) {
                    $this->response->setContent('** Ignoring duplicate older event: ' . $queuedEventsForModule[$result['object_id']]['id'] . PHP_EOL);
                    $this->response->send();
                }
                if (isset($deferredEvents[$result['module_name']][$result['object_id']])) {
                    foreach ($deferredEvents[$result['module_name']][$result['object_id']] as $deferredEvent) {
                        $this->eventRepository->remove($deferredEvent);
                        $this->response->setContent('** Removing older deferred event: ' . $deferredEvent->getEventId() . PHP_EOL);
                        $this->response->send();
                    }
                }
                $lastEventId = max($lastEventId, $result['id']);
                $queuedEventsForModule[$result['object_id']] = $result;
            }
            foreach ($queuedEventsForModule as $result) {
                $this->queueEvent($module, $result);
            }

            if ($lastEventId > 0) {
                $module->setLastReceivedEventId($lastEventId);
                $this->moduleRepository->update($module);
            }
        }

        $this->objectManager->get(PersistenceManagerInterface::class)->persistAll();

    }

    /**
     * Execute all deferred and pending events
     *
     * @param bool $sync If true, will start executing events from earliest possible. If false, will continue from last processed event.
     * @param string $module If passed can be used to only sync one module, using the module or connector name it has in 4AP.
     * @param string $exclude Exclude a list of modules from processing (CSV string module names)
     * @param bool $force If set, forces the sync to run regardless of lock and will neither lock nor unlock the task
     */
    public function execute($sync = false, $module = null, $exclude = null, $force = false)
    {


        $activeModules = $this->getActiveModuleOrModules($module);

        foreach ($activeModules as $module) {

            /** @var Module $module */
            if ($sync && $module->getLastReceivedEventId() > 0) {
                $module->setLastEventId(0);
            }
        }

        foreach ($this->eventRepository->findByStatus('deferred') as $event) {
            if (in_array($event->getModule()->getModuleName(), $exclude ?? [])) {
                continue;
            }
            if (!in_array($event->getModule(), $activeModules)) {
                continue;
            }
            $deferredEvents[$event->getModule()->getModuleName()][$event->getObjectId()][] = $event;
            if ($event->getNextRetry() < time()) {
                $this->processEvent($event, false);
            }
        }

        $pending = $this->eventRepository->findByStatus('pending')->toArray();
        foreach ($pending as $event) {
            $this->processEvent($event, true);
        }

        try {
            $this->objectManager->get(PersistenceManagerInterface::class)->persistAll();
        } catch (\Exception $error) {
            $this->logProblem($error);
        }

    }

    /**
     * @param ApiClient $client
     * @param string $connectorName
     * @param int $lastEventId
     * @return array
     */
    protected function readAllPendingEvents(ApiClient $client, $connectorName, $lastEventId = 0)
    {
        $done = false;
        $allEvents = [];
        try {
            while (($events = $client->getEvents($connectorName, $lastEventId)) && count($events) && !$done) {
                foreach ($events as $event) {
                    //echo 'Read: ' . $connectorName . ':' . $event['id'] . PHP_EOL;
                    $lastEventId = $event['id'];
                    if (isset($allEvents[$lastEventId])) {
                        $done = true;
                        break;
                    }
                    $allEvents[$lastEventId] = $event;
                }
            }
        } catch (\Exception $error) {
            $this->logProblem($error);
        }
        return $allEvents;
    }

    /**
     * @param string $moduleName
     * @return Module[]
     */
    protected function getActiveModuleOrModules($moduleName = null)
    {
        $activeModules = [];
        /** @var Server[] $servers */
        $servers = $this->serverRepository->findByActive(true);
        foreach ($servers as $server) {
            /** @var Module[] $modules */
            $modules = $server->getModules();
            foreach ($modules as $configuredModule) {
                if ($moduleName && ($configuredModule->getModuleName() !== $moduleName && $configuredModule->getConnectorName() !== $moduleName)) {
                    continue;
                }
                $activeModules[] = $configuredModule;
            }
        }
        return $activeModules;
    }

    protected function processAllPendingAndDeferredEvents($updateEventId = true)
    {
        $pending = $this->eventRepository->findByStatus('pending')->toArray();
        $deferred = $this->eventRepository->findByStatus('deferred')->toArray();

        // CD 5/12/17 Disabled: causes responses for full set of entries to be logged in database.
        //$this->collectPreloadDataForObjectsInEvents(array_merge_recursive($pending, $deferred));

        // Handle any events that were deferred - which may cause some to be deferred again:
        foreach ($deferred as $event) {
            if ($event->getNextRetry() < time()) {
                $this->processEvent($event, $updateEventId);
            }
        }

        // Handle new, pending events first, which may cause some to be deferred:
        foreach ($pending as $event) {
            $this->processEvent($event, $updateEventId);
        }

        $this->objectManager->get(PersistenceManagerInterface::class)->persistAll();
    }

    /**
     * @param Event[] $events
     * @return array
     */
    protected function collectPreloadDataForObjectsInEvents(array $events)
    {
        if (empty($events)) {
            return [];
        }
        $indexed = [];
        $module = $events[0]->getModule();
        $client = $module->getServer()->getClient();
        foreach ($events as $event) {
            if ($event->getEventType() !== 'delete') {
                $objectId = $event->getObjectId();
                $indexed[$objectId] = $event;
            }
        }
        try {
            $data = $client->getBeans(array_keys($indexed), $module->getConnectorName());
            foreach ($data['result'] as $result) {
                $objectId = $result['id'];
                $indexed[$objectId]->setBeanData($result);
            }
        } catch (\Exception $error) {
            return [];
        }
    }

    /**
     * @param Module $module
     * @param array $result
     * @return Event
     */
    protected function queueEvent($module, $result)
    {
        $event = new Event();
        $event->setModule($module);
        $event->setEventId($result['id']);
        $event->setObjectId($result['object_id']);
        $event->setEventType(Event::resolveEventType($result['event_type']));
        $this->eventRepository->add($event);

        return $event;
    }

    /**
     * Locks the sync to avoid multiple processes
     *
     * NB: Cannot use TYPO3 LockFactory here, will not consistently create locks
     * on docker setups.
     *
     * @return bool
     * @throws LockCreateException
     */
    public function lock()
    {
        $path = $this->getLockFilePath();
        if (file_exists($path)) {
            throw new LockCreateException('Cannot acquire lock for 4AP sync');
        }
        return touch($path);
    }

    /**
     * Unlock the sync after process is complete
     *
     * NB: Cannot use TYPO3 LockFactory here, will not consistently create locks
     * on docker setups.
     *
     * @param int $requiredAge Number of seconds, required minimum age of the lock file before removal will be allowed.
     * @return bool
     */
    public function unlock($requiredAge = 0)
    {
        $lockFile = $this->getLockFilePath();
        if (!file_exists($lockFile)) {
            return false;
        }
        $age = time() - filemtime($lockFile);
        if ($age >= $requiredAge) {
            return unlink($lockFile);
        }
        $this->response->setContent(
            sprintf(
                'Lock file was not removed; it is younger than the required age for removal. %d seconds too young.',
                $requiredAge - $age
            )
        );
        $this->response->send();
        return false;
    }

    protected function getLockFilePath()
    {
        return GeneralUtility::getFileAbsFileName('typo3temp/var/transient/') . 'lock_4ap_sync.lock';
    }

    public function logProblem(\Exception $exception)
    {
        GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__)->critical(
            $exception->getMessage(),
            [
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]
        );
    }

    /**
     * @param Event $event
     * @param bool $updateEventId
     */
    public function processEvent($event, $updateEventId = true)
    {
        $this->response->setContent(
            'Processing ' . $event->getStatus() . ' event "' . $event->getModule()->getModuleName() . ':' . $event->getEventId() . '" - ' .
            $event->getEventType() . ' ' . $event->getObjectId() . PHP_EOL
        );
        $this->response->send();
        $client = $event->getModule()->getServer()->getClient();
        try {

            $mapper = $event->getModule()->getMapper();
            $responseData = [];
            if ($event->getEventType() !== 'delete') {
                if (empty($event->getBeanData())) {
                    $responseData = $client->getBeans(
                        [
                            $event->getObjectId()
                        ],
                        $event->getModule()->getConnectorName()
                    );
                } else {
                    $responseData = ['result' => [$event->getBeanData()]];
                }
            }

            // Update the Module's last recorded event ID, but only if the event ID was higher. This allows
            // deferred events to execute without lowering the last recorded event ID which would cause
            // duplicate event processing on the next run.
            if ($updateEventId) {
                $event->getModule()->setLastEventId(max($event->getEventId(), $event->getModule()->getLastEventId()));
            }
            if ($mapper->import($responseData, $event)) {
                // This method returns TRUE if any property caused problems that were also logged. When this
                // happens, throw a deferral exception and let the catch statement below handle deferral.
                throw new DeferralException(
                    'Property mapping problems occurred and have been logged - the object was partially mapped and will be retried',
                    1528129226
                );
            }
            $event->setRetries(0);
            $event->setStatus('claimed');
            $event->setMessage('Successfully executed - no additional output available');
        } catch (DeferralException $error) {
            // The system was unable to map properties, most likely because of an unresolvable relation.
            // Skip the event for now; process it later.
            $skippedUntil = $event->getSkipUntil();
            $ttl = (int)($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fourallportal']['eventDeferralTTL'] ?? 86400);
            $now = time();
            $event->setMessage($error->getMessage() . ' (code: ' . $error->getCode() . ')');
            $event->setStatus('deferred');
            $event->setRetries((int)$event->getRetries() + 1);
            // Next retry time: current time plus, plus number of retries times half an hour, plus/minus 600 seconds to
            // stagger event execution and prevent the bulk from executing at the same time if many deferrals happen
            // during a full sync. So, deferral waiting time increases incrementally from no less than 20 minutes to
            // around 10 hours maximum.
            $event->setNextRetry($now + ((min($event->getRetries(), 20)) * 1800) + rand(-600, 600));
            if ($skippedUntil === 0) {
                // Assign a TTL, after which if the event still causes a problem it gets marked as failed.
                $skippedUntil = $now + $ttl;
                $event->setSkipUntil($skippedUntil);
            } elseif ($skippedUntil < $now) {
                // Event has been deferred too long and still causes an error. Mark it as failed (and reset the
                // deferral TTL so that deferral is again allowed, should the event be retried via BE module).
                $event->setSkipUntil(0);
                $event->setRetries(0);
                $event->setStatus('failed');
            }
        } catch (\Exception $exception) {
            $this->logProblem($exception);
            $event->setStatus('failed');
            $event->setRetries(0);
            $event->setMessage($exception->getMessage() . ' (code: ' . $exception->getCode() . ')' . $exception->getFile() . ':' . $exception->getLine());
            if ($updateEventId) {
                $event->getModule()->setLastEventId(max($event->getEventId(), $event->getModule()->getLastEventId()));
            }
        }
        $responseMetadata = $client->getLastResponse();
        $event->setHeaders($responseMetadata['headers']);
        $event->setUrl($responseMetadata['url']);
        $event->setResponse($responseMetadata['response']);
        $event->setPayload($responseMetadata['payload']);
        $this->eventRepository->update($event);
        try {
            $this->objectManager->get(PersistenceManager::class)->persistAll();
        } catch (\Exception $error) {
            $this->logProblem($error);
        }
    }
}
