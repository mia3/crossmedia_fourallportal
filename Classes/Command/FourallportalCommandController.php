<?php
namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\DynamicModel\DynamicModelGenerator;
use Crossmedia\Fourallportal\DynamicModel\DynamicModelRegister;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

class FourallportalCommandController extends CommandController
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

    /**
     * Update models
     *
     * Updates local model classes with properties as specified by
     * the mapping information and model information from the API.
     * Uses the Server and Module configurations in the system and
     * consults the Mapping class to identify each model that must
     * be updated, then uses the DynamicModelHandler to generate
     * an abstract model class to use with each specific model.
     *
     * A special class loading function must be used in the model
     * before it can use the dynamically generated base class. See
     * the provided README.md file for more information about this.
     */
    public function updateModelsCommand()
    {
        GeneralUtility::makeInstance(ObjectManager::class)->get(DynamicModelGenerator::class)->generateAbstractModelsForAllModules();
    }

    /**
     * Generates all configuration
     *
     * Shortcut method for calling all of the three specific
     * generate commands to generate static configuration files for
     * all dynamic-model-enabled modules' entities.
     *
     * @param string $entityClassName
     */
    public function generateCommand($entityClassName = null)
    {
        $this->generateSqlSchemaCommand();
        $this->generateTableConfigurationCommand($entityClassName);
        $this->generateAbstractModelClassCommand($entityClassName);
    }

    /**
     * Generate TCA for model
     *
     * This command can be used instead or or together with the
     * dynamic model feature to generate a TCA file for a particular
     * entity, by its class name.
     *
     * Internally the class name is analysed to determine the
     * extension it belongs to, and makes an assumption about the
     * table name. The command then writes the generated TCA to the
     * exact TCA configuration file (by filename convention) and
     * will overwrite any existing TCA in that file.
     *
     * Should you need to adapt individual properties such as the
     * field used for label, the icon path etc. please use the
     * Configuration/TCA/Overrides/$tableName.php file instead.
     *
     * @param string $entityClassName
     */
    public function generateTableConfigurationCommand($entityClassName = null)
    {
        foreach ($this->getEntityClassNames($entityClassName) as $entityClassName) {
            $tca = DynamicModelGenerator::generateAutomaticTableConfigurationForModelClassName($entityClassName);
            $table = $this->objectManager->get(DataMapper::class)->getDataMap($entityClassName)->getTableName();
            $extensionKey = $this->getExtensionKeyFromEntityClasName($entityClassName);

            // Note: although extPath() supports a second argument we concatenate to prevent file exists. It may not exist yet!
            $targetFilePathAndFilename = ExtensionManagementUtility::extPath($extensionKey) . 'Configuration/TCA/' . $table . '.php';
            $targetFileContent = '<?php' . PHP_EOL . 'return ' . var_export($tca, true) . ';' . PHP_EOL;
            GeneralUtility::writeFile(
                $targetFilePathAndFilename,
                $targetFileContent
            );
        }
    }

    /**
     * Generate abstract entity class
     *
     * This command can be used as substitute for the automatic
     * model class generation feature. Each entity class generated
     * with this command prevents usage of the dynamically created
     * class (which still gets created!). To re-enable dynamic
     * operation simply remove the generated abstract class again.
     *
     * Generates an abstract PHP class in the same namespace as
     * the input entity class name. The abstract class contains
     * all of the dynamically generated properties associated with
     * the Module.
     *
     * @param string $entityClassName
     */
    public function generateAbstractModelClassCommand($entityClassName = null)
    {
        $dynamicModelGenerator = $this->objectManager->get(DynamicModelGenerator::class);

        $modulesByEntityClassName = [];
        foreach ($dynamicModelGenerator->getAllConfiguredModules() as $module) {
            if ($module->isEnableDynamicModel()) {
                $modulesByEntityClassName[$module->getMapper()->getEntityClassName()] = $module;
            }
        }

        foreach ($this->getEntityClassNames($entityClassName) as $entityClassName) {
            if (!isset($modulesByEntityClassName[$entityClassName])) {
                $this->response->appendContent('Cannot generate model for ' . $entityClassName . ' - has no configured module to handle the entity' . PHP_EOL);
                continue;
            }
            $extensionKey = $this->getExtensionKeyFromEntityClasName($entityClassName);
            $module = $modulesByEntityClassName[$entityClassName];
            $sourceCode = $dynamicModelGenerator->generateAbstractModelForModule($module);
            $abstractClassName = 'Abstract' . substr($entityClassName, strrpos($entityClassName, '\\') + 1);
            $targetFileContent = '<?php' . PHP_EOL . $sourceCode . PHP_EOL;
            $targetFilePathAndFilename = ExtensionManagementUtility::extPath($extensionKey) . 'Classes/Domain/Model/' . $abstractClassName . '.php';
            GeneralUtility::writeFile(
                $targetFilePathAndFilename,
                $targetFileContent
            );
        }
    }

    /**
     * Generate additional SQL schema file
     *
     * This command can be used as substitute for the automatic
     * SQL schema generation - using it disables the analysis of
     * the Module to read schema properties. If used, should be
     * combined with both of the other "generate" commands from
     * this package, to create a completely static set of assets
     * based on the configured Modules and prevent dynamic changes.
     *
     * Generates all schemas for all modules, and generates a static
     * SQL schema file in the extension to which the entity belongs.
     * The SQL schema registration hook then circumvents the normal
     * schema fetching and uses the static schema instead, when the
     * extension has a static schema.
     */
    public function generateSqlSchemaCommand()
    {
        $dynamicModelGenerator = $this->objectManager->get(DynamicModelGenerator::class);
        $modulesByExtensionKey = [];
        foreach ($dynamicModelGenerator->getAllConfiguredModules() as $name => $module) {
            if (!$module->isEnableDynamicModel()) {
                continue;
            }
            $extensionKey = $this->getExtensionKeyFromEntityClasName($module->getMapper()->getEntityClassName());
            if (!isset($modulesByExtensionKey[$extensionKey])) {
                $modulesByExtensionKey[$extensionKey] = [$module];
            } else {
                $modulesByExtensionKey[$extensionKey][] = $module;
            }
        }
        foreach ($modulesByExtensionKey as $extensionKey => $groupedModules) {
            $targetFileContent = implode(PHP_EOL, $dynamicModelGenerator->generateSchemasForModules($groupedModules));
            $targetFilePathAndFilename = ExtensionManagementUtility::extPath($extensionKey) . 'Configuration/SQL/DynamicSchema.sql';
            GeneralUtility::mkdir_deep(dirname($targetFilePathAndFilename));
            GeneralUtility::writeFile(
                $targetFilePathAndFilename,
                $targetFileContent
            );
        }
    }

    /**
     * @param string $entityClassName
     * @return array
     */
    protected function getEntityClassNames($entityClassName)
    {
        if ($entityClassName) {
            $entityClassNames = [$entityClassName];
        } else {
            $entityClassNames = DynamicModelRegister::getModelClassNamesRegisteredForAutomaticHandling();
        }
        return $entityClassNames;
    }

    /**
     * @param string $entityClassName
     * @return string
     */
    protected function getExtensionKeyFromEntityClasName($entityClassName)
    {
        $entityClassNameParts = explode('\\', $entityClassName);
        $entityClassNameBase = array_slice($entityClassNameParts, 0, -3);
        $extensionName = array_pop($entityClassNameBase);
        return GeneralUtility::camelCaseToLowerCaseUnderscored($extensionName);
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
     */
    public function replayCommand($events = 1, $module = null)
    {
        foreach ($this->getActiveModuleOrModules($module) as $module) {
            $eventQuery = $this->eventRepository->createQuery();
            $eventQuery->matching(
                $eventQuery->equals('module', $module->getUid())
            );
            $eventQuery->setLimit($events);
            $eventQuery->setOrderings(['event_id' => 'DESC']);
            foreach ($eventQuery->execute() as $event) {
                $event->setStatus('pending');
                $this->eventRepository->update($event);
            }
        }
        $this->objectManager->get(PersistenceManagerInterface::class)->persistAll();
        $this->processAllPendingAndDeferredEvents();
    }

    /**
     * Sync data
     *
     * Execute this to synchronise events from the PIM API.
     *
     * @param boolean $sync Set to "1" to trigger a full sync
     * @param string $module If passed can be used to only sync one module, using the module or connector name it has in 4AP.
     */
    public function syncCommand($sync = false, $module = null)
    {
        foreach ($this->getActiveModuleOrModules($module) as $module) {
            $client = $module->getServer()->getClient();
            /** @var Module $configuredModule */
            if (!$sync && $module->getLastEventId() > 0) {
                $results = $client->getEvents($module->getConnectorName(), $module->getLastEventId());
            } else {
                $moduleEvents = $this->eventRepository->findByModule($module->getUid());
                foreach ($moduleEvents as $moduleEvent) {
                    $this->eventRepository->remove($moduleEvent);
                }
                $this->objectManager->get(PersistenceManagerInterface::class)->persistAll();
                $results = $client->synchronize($module->getConnectorName());
            }
            foreach ($results as $result) {
                $this->response->setContent('Receiving event ID "' . $result['id'] . '" from connector "' . $module->getConnectorName() . '"' . PHP_EOL);
                if (!$result['id']) {
                    $this->response->appendContent(var_export($result, true) . PHP_EOL);
                }
                $this->response->send();
                $this->queueEvent($module, $result);
            }
            $this->moduleRepository->update($module);
        }

        $this->objectManager->get(PersistenceManagerInterface::class)->persistAll();
        $this->processAllPendingAndDeferredEvents();
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

    protected function processAllPendingAndDeferredEvents()
    {
        // Handle new, pending events first, which may cause some to be deferred:
        foreach ($this->eventRepository->findByStatus('pending') as $event) {
            $this->processEvent($event);
        }

        // Then handle any events that were deferred - which may cause some to be deferred again:
        foreach ($this->eventRepository->findByStatus('deferred') as $event) {
            $this->processEvent($event);
        }
    }

    /**
     * @param Event $event
     */
    public function processEvent($event)
    {
        $client = $event->getModule()->getServer()->getClient();
        try {
            $mapper = $event->getModule()->getMapper();
            $responseData = $client->getBeans(
                [
                    $event->getObjectId()
                ],
                $event->getModule()->getConnectorName()
            );
            $mapper->import($responseData, $event);
            $event->setStatus('claimed');
            $event->setMessage('Successfully executed - no additional output available');
            // Update the Module's last recorded event ID, but only if the event ID was higher. This allows
            // deferred events to execute without lowering the last recorded event ID which would cause
            // duplicate event processing on the next run.
            $event->getModule()->setLastEventId(max($event->getEventId(), $event->getModule()->getLastEventId()));
        } catch (\InvalidArgumentException $error) {
            // The system was unable to map properties, most likely because of an unresolvable relation.
            // Skip the event for now; process it later.
            $event->setStatus('deferred');
            $event->setMessage($error->getMessage() . ' (code: ' . $error->getCode() . ')');
        } catch(\Exception $exception) {
            $event->setStatus('failed');
            $event->setMessage($exception->getMessage() . ' (code: ' . $exception->getCode() . ')');
        }
        $responseMetadata = $client->getLastResponse();
        $event->setHeaders($responseMetadata['headers']);
        $event->setUrl($responseMetadata['url']);
        $event->setResponse($responseMetadata['response']);
        $event->setPayload($responseMetadata['payload']);
        $this->eventRepository->update($event);
        $this->objectManager->get(PersistenceManagerInterface::class)->persistAll();
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
}
