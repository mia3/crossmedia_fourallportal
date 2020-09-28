<?php
namespace Crossmedia\Fourallportal\Service;

use Crossmedia\Fourallportal\Domain\Dto\SyncParameters;
use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Domain\Repository\EventRepository;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\Domain\Repository\ServerRepository;
use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Mapping\DeferralException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Locking\Exception\LockCreateException;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Cli\Response;
use TYPO3\CMS\Extbase\Mvc\ResponseInterface;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Scheduler\Task;
use TYPO3\CMS\Scheduler\Scheduler;

class EventExecutionService implements SingletonInterface
{
    /**
     * @var ServerRepository
     * */
    protected $serverRepository = null;

    /**
     * @var EventRepository
     * */
    protected $eventRepository = null;

    /**
     * @var ModuleRepository
     * */
    protected $moduleRepository = null;

    /**
     * @var LoggingService
     * */
    protected $loggingService = null;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    public function injectEventRepository(EventRepository $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    public function injectModuleRepository(ModuleRepository $moduleRepository)
    {
        $this->moduleRepository = $moduleRepository;
    }

    public function injectServerRepository(ServerRepository $serverRepository)
    {
        $this->serverRepository = $serverRepository;
    }

    public function injectLoggingService(LoggingService $loggingService)
    {
        $this->loggingService = $loggingService;
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

    public function sync(SyncParameters $parameters)
    {
        try {
            $parameters->startExecution();
            if ($parameters->getSync()) {
                $this->performSync($parameters);
            }
            if ($parameters->getExecute()) {
                $this->performExecute($parameters);
            }
        } catch (ApiException $error) {
            $this->unlock();
        }
    }

    protected function performSync(SyncParameters $parameters)
    {
        $fullSync = $parameters->getFullSync();
        $module = $parameters->getModule();
        $exclude = $parameters->getExclude();

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
        if ($fullSync && !$module && empty($exclude)) {
            $GLOBALS['TYPO3_DB']->exec_TRUNCATEquery('tx_fourallportal_domain_model_event');
        }

        foreach ($activeModules as $module) {
            if (!$module->verifySchemaVersion()) {
                $this->loggingService->logSchemaActivity(
                    sprintf(
                        'Remote config hash "%s" does not match local "%s" - skipping SYNC of module "%s"',
                        $module->getConnectorConfiguration()['config_hash'],
                        $module->getConfigHash(),
                        $module->getModuleName()
                    ),
                    GeneralUtility::SYSLOG_SEVERITY_FATAL
                );
                continue;
            }

            $client = $module->getServer()->getClient();
            if (empty($module->getModuleName())) {
                $connectorConfig = $client->getConnectorConfig($module->getConnectorName());
                $module->setModuleName($connectorConfig['moduleConfig']['module_name']);
            }

            if (in_array($module->getModuleName(), $exclude)) {
                continue;
            }

            if ($fullSync) {
                $module->setLastReceivedEventId(1);
                $moduleEvents = $this->eventRepository->findByModule($module->getUid());
                foreach ($moduleEvents as $moduleEvent) {
                    $this->eventRepository->remove($moduleEvent);
                }
                $this->moduleRepository->update($module);
                $this->objectManager->get(PersistenceManagerInterface::class)->persistAll();
            }

            $lastEventId = $module->getLastReceivedEventId();
            $results = $this->readAllPendingEvents($client, $module->getConnectorName(), $module->getLastReceivedEventId());
            $queuedEventsForModule = [];
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

            $module->setLastReceivedEventId($lastEventId);
            $this->moduleRepository->update($module);
        }

        $this->objectManager->get(PersistenceManagerInterface::class)->persistAll();
    }

    public function execute(SyncParameters $parameters)
    {
        try {
            $this->performExecute($parameters);
        } catch (ApiException $error) {
            $this->unlock();
        }
    }

    protected function performExecute(SyncParameters $parameters)
    {
        $sync = $parameters->getSync();
        $module = $parameters->getModule();

        $activeModules = $this->getActiveModuleOrModules($module);

        foreach ($activeModules as $module) {
            if (!$module->verifySchemaVersion()) {
                $message = sprintf(
                    'Remote config hash "%s" does not match local "%s"',
                    $module->getConnectorConfiguration()['config_hash'],
                    $module->getConfigHash()
                );
                $this->loggingService->logSchemaActivity($message, GeneralUtility::SYSLOG_SEVERITY_FATAL);
                $parameters->excludeModule($module->getModuleName());
                continue;
            }

            /** @var Module $module */
            if ($sync && $module->getLastReceivedEventId() > 0) {
                $module->setLastEventId(0);
            }
        }

        $maxEvents = min($parameters->getEventLimit(), 100);
        if ($maxEvents === 0) {
            $maxEvents = 100;
        }
        $maxDeferredEvents = floor($maxEvents / 5);
        while ($parameters->shouldContinue()) {
            if ($maxDeferredEvents > 0) {
                while ($parameters->shouldContinue() && ($events = $this->eventRepository->findDeferred($maxEvents)) && $events->count() > 0) {
                    echo 'Processing batch of ' . $events->count() . ' deferred events...' . PHP_EOL;
                    $this->processEvents($parameters, $events);
                }
            }

            while ($parameters->shouldContinue() && ($events = $this->eventRepository->findByStatus('pending', $maxEvents, false)) && $events->count() > 0) {
                echo 'Processing batch of ' . $events->count() . ' pending events...' . PHP_EOL;
                $this->processEvents($parameters, $events);
            }
            if ($events && $events->count() === 0) {
                break;
            }
        }
    }

    protected function processEvents(SyncParameters $parameters, QueryResultInterface $events)
    {
        foreach ($events as $event) {
            if (!$parameters->shouldContinue()) {
                return;
            }
            if ($parameters->isModuleExcluded($event->getModule()->getModuleName())) {
                continue;
            }
            $this->processEvent($event, true, $parameters);
        }

        // Trigger post-execution hook
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['fourallportal']['postEventExecution'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['fourallportal']['postEventExecution'] as $postExecutionHookClass) {
                GeneralUtility::makeInstance($postExecutionHookClass)->postEventExecution($events->toArray());
            }
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
        // Determine delay between 1,000 event batches: if $lastEventId is zero this causes the getEvents
        // call to recreate the event queue on the remote service. If this code then loops and continuously
        // calls getEvents, it is possible to reach a state where zero events are returned because the event
        // queue on the remote service is not fully recreated. Subsequent calls to getEvents then returns
        // additional events, causing problems with the local queue's consistency.
        // Introducing a wait between each batch when $lastEventId is zero gives the remote service enough
        // time to fully recreate the event queue, and the loop then won't exit until every event is recreated
        // and fetched.
        $sleep = ((int) $lastEventId === 0);
        $allEvents = [];
        while (($events = $client->getEvents($connectorName, $lastEventId)) && count($events)) {
            foreach ($events as $event) {
                $lastEventId = $event['id'];
                $allEvents[$lastEventId] = $event;
            }
            if ($sleep) {
                sleep(10);
            }
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
            if (!$server->isActive()) {
                continue;
            }
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

    /**
     * @param Module $module
     * @param array $result
     * @return Event
     */
    protected function queueEvent($module, $result)
    {
        $event = $this->eventRepository->findOneByModuleAndEventId($module, (int) $result['id']);
        $new = false;
        if (!$event) {
            $event = new Event();
            $new = true;
        } elseif ($event->getStatus() === 'claimed') {
            return $event;
        }

        $event->setModule($module);
        $event->setCrdate(strtotime($result['mod_time']));
        $event->setEventId($result['id']);
        $event->setObjectId($result['object_id']);
        $event->setEventType(Event::resolveEventType($result['event_type']));
        $event->setStatus('pending');

        if ($new) {
            $this->eventRepository->add($event);
        } else {
            $this->eventRepository->update($event);
        }

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
            $this->resetSchedulerTask($requiredAge);
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

    protected function resetSchedulerTask($requiredAge)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_scheduler_task');
        $queryBuilder->select('uid')->from('tx_scheduler_task');
        $result = $queryBuilder->execute();
        $deadAge = time() - $requiredAge;
        $scheduler = GeneralUtility::makeInstance(Scheduler::class);
        while (($taskRecord = $result->fetch())) {
            $task = $scheduler->fetchTask($taskRecord['uid']);
            if ($task->isExecutionRunning() && $task instanceof Task && $task->getCommandIdentifier() === 'fourallportal:fourallportal:sync' && $task->getExecution()->getStart() <= $deadAge) {
                $task->unmarkAllExecutions();
                $task->setRunOnNextCronJob(true);
                $task->save();
            }
        }
    }

    protected function getLockFilePath()
    {
        return GeneralUtility::getFileAbsFileName('typo3temp/var/locks/') . 'lock_4ap_sync.lock';
    }

    /**
     * @param Event $event
     * @param bool $updateEventId
     * @param SyncParameters|null $parameters
     */
    public function processEvent($event, $updateEventId = true, ?SyncParameters $parameters = null)
    {
        if ($event->isProcessing()) {
            return;
        }

        $this->response->setContent(
            'Processing ' . $event->getStatus() . ' event "' . $event->getModule()->getModuleName() . ':' . $event->getEventId() . '" - ' .
            $event->getEventType() . ' ' . $event->getObjectId() . PHP_EOL
        );

        $event->setProcessing(true);

        if (method_exists($this->response, 'send')) {
            $this->response->send();
        }
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
            $this->loggingService->logEventActivity($event, 'Event was executed');
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
            $this->loggingService->logEventActivity($event, 'Event was deferred', GeneralUtility::SYSLOG_SEVERITY_WARNING);
        } catch (\Exception $exception) {
            $event->setStatus('failed');
            $event->setRetries(0);
            $event->setMessage($exception->getMessage() . ' (code: ' . $exception->getCode() . ')' . $exception->getFile() . ':' . $exception->getLine());
            if ($updateEventId) {
                $event->getModule()->setLastEventId(max($event->getEventId(), $event->getModule()->getLastEventId()));
            }
            $this->loggingService->logEventActivity($event, 'System error: ' . $exception->getMessage(), GeneralUtility::SYSLOG_SEVERITY_WARNING);
        }
        $responseMetadata = $client->getLastResponse();
        $event->setHeaders($responseMetadata['headers']);
        $event->setUrl($responseMetadata['url']);
        $event->setResponse($responseMetadata['response']);
        $event->setPayload($responseMetadata['payload']);
        $event->setProcessing(false);
        $this->eventRepository->update($event);
        try {
            $this->objectManager->get(PersistenceManager::class)->persistAll();
        } catch (\Exception $error) {
            $this->loggingService->logEventActivity($event, 'System error: ' . $exception->getMessage(), GeneralUtility::SYSLOG_SEVERITY_WARNING);
        }

        if ($parameters) {
            $parameters->countExecutedEvent();
        }
    }
}
