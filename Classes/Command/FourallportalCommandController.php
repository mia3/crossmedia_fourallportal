<?php
namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Domain\Dto\SyncParameters;
use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\DynamicModel\DynamicModelGenerator;
use Crossmedia\Fourallportal\DynamicModel\DynamicModelRegister;
use Crossmedia\Fourallportal\Mapping\DeferralException;
use Crossmedia\Fourallportal\Service\ApiClient;
use Crossmedia\Fourallportal\Service\EventExecutionService;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Locking\Exception\LockCreateException;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

class FourallportalCommandController extends CommandController
{
    /**
     * @var EventExecutionService
     */
    protected $eventExecutionService;

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

    public function injectEventExecutionService(EventExecutionService $eventExecutionService)
    {
        $this->eventExecutionService = $eventExecutionService;
    }

    /**
     * Pin PIM schema version
     *
     * Pins the PIM schema version, updating all local modules to use the
     * version of configuration that is currently live on the configured
     * remote server.
     *
     * Used when a schema version mismatch prevents PIM sync from running.
     */
    public function pinschemaCommand()
    {
        foreach ($this->moduleRepository->findAll() as $module) {
            if ($module->getServer()->isActive()) {
                $module->pinSchemaVersion();
            }
        }
        GeneralUtility::makeInstance(ObjectManager::class)->get(PersistenceManager::class)->persistAll();
    }

    /**
     * Run tests
     *
     * Runs tests on schema and response consistency and performs tracking
     * of basic response changes, i.e. simple diffs of which properties
     * are included in the response.
     *
     * Outputs streaming YAML.
     *
     * @param bool $onlyFailed If TRUE, only outputs failed properties.
     * @param bool $withHistory If TRUE, includes a tracking history of schema/response consistency for each module.
     */
    public function testCommand($onlyFailed = false, $withHistory = true)
    {
        foreach ($this->moduleRepository->findAll() as $module) {
            $testObjectUuid = $module->getTestObjectUuid();
            $this->response->setContent($module->getModuleName() . ':');
            if (empty($testObjectUuid)) {
                $this->response->appendContent(' false');
                continue;
            }
            $this->response->appendContent(PHP_EOL);
            $this->response->appendContent('  fields:' . PHP_EOL);
            $this->response->send();
            $bean = $module->getServer()->getClient()->getBeans($testObjectUuid, $module->getConnectorName());
            $fieldsToLoad = $module->getConnectorConfiguration()['fieldsToLoad'];
            foreach ($fieldsToLoad as $fieldName => $configuration) {
                $this->response->setContent('    ' . $fieldName . ':');
                if (isset($bean['info']['not_accessible_ids'])) {
                    $this->response->appendContent(' "Not found: ' . $testObjectUuid . '"' . PHP_EOL);
                    $this->response->send();
                } elseif (array_key_exists($fieldName, $bean['result'][0]['properties'] ?? [])) {
                    if (!$onlyFailed) {
                        $this->response->appendContent(' true' . PHP_EOL);
                        $this->response->send();
                    }
                } else {
                    $this->response->appendContent(' false' . PHP_EOL);
                    $this->response->send();
                }
            }
            if ($withHistory && isset($bean['result'][0]['properties'])) {
                $this->trackHistory($module, $bean['result'][0]['properties']);
                $history = $this->getModuleHistory($module);
                $this->response->setContent('  history:');
                if (empty($history)) {
                    $this->response->appendContent(' false' . PHP_EOL);
                    $this->response->send();
                    continue;
                }
                $this->response->appendContent(PHP_EOL);
                $this->response->send();
                foreach ($history as $date => list (, $addedLoad, $removedLoad, , $addedProperties, $removedProperties)) {
                    $touched = false;
                    $this->response->setContent('    ' . $date . ':' . PHP_EOL);
                    if (!empty($addedLoad)) {
                        $this->response->appendContent('      - addedFieldsToLoad: ["' . implode('", "', $addedLoad) . '"]' . PHP_EOL);
                        $touched = true;
                    }
                    if (!empty($removedLoad)) {
                        $this->response->appendContent('      - removedFieldsToLoad: ["' . implode('", "', $removedLoad) . '"]' . PHP_EOL);
                        $touched = true;
                    }
                    if (!empty($addedProperties)) {
                        $this->response->appendContent('      - addedProperties: ["' . implode('", "', $addedProperties) . '"]' . PHP_EOL);
                        $touched = true;
                    }
                    if (!empty($removedProperties)) {
                        $this->response->appendContent('      - removedProperties: ["' . implode('", "', $removedProperties) . '"]' . PHP_EOL);
                        $touched = true;
                    }
                    if ($touched) {
                        $this->response->send();
                    }
                    $this->response->setContent('');
                }
            }
        }
        $this->response->appendContent(PHP_EOL);
        $this->response->send();
    }

    protected function trackHistory(Module $module, array $properties)
    {
        $history = $this->getModuleHistory($module);
        $fieldsToLoad = $module->getConnectorConfiguration()['fieldsToLoad'];
        $currentFieldsToLoad = array_keys($fieldsToLoad);
        $currentProperties = array_keys($properties);
        $mostRecent = end($history);
        reset($history);
        if (!$mostRecent) {
            $history[date('Ymd_Hi')] = [
                $currentFieldsToLoad,
                [],
                [],
                $currentProperties,
                [],
                []
            ];
        } else {
            list ($mostRecentFieldsToLoad, , , $mostRecentProperties, , ) = $mostRecent;
            if ($mostRecentFieldsToLoad != $currentFieldsToLoad || $mostRecentProperties != $currentProperties) {
                $diffFieldsToLoad = array_diff($mostRecentFieldsToLoad, $currentFieldsToLoad);
                $diffProperties = array_diff($mostRecentProperties, $currentProperties);
                $addedFieldsToLoad = [];
                $removedFieldsToLoad = [];
                foreach ($diffFieldsToLoad as $diffPropertyName) {
                    if (!array_key_exists($diffPropertyName, $currentFieldsToLoad)) {
                        $addedFieldsToLoad[] = $diffPropertyName;
                    } else {
                        $removedFieldsToLoad[] = $diffPropertyName;
                    }
                }
                $addedProperties = [];
                $removedProperties = [];
                foreach ($diffProperties as $diffPropertyName) {
                    if (!array_key_exists($diffPropertyName, $currentProperties)) {
                        $addedProperties[] = $diffPropertyName;
                    } else {
                        $removedProperties[] = $diffPropertyName;
                    }
                }
                $history[date('Ymd_Hi')] = [
                    $currentFieldsToLoad,
                    $addedFieldsToLoad,
                    $removedFieldsToLoad,
                    $currentProperties,
                    $addedProperties,
                    $removedProperties
                ];
            }
        }

        $historyFile = $this->getHistoryFilename($module);
        file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));
    }

    /**
     * Returns an array of arrays each containing exactly six properties:
     *
     * - a list of all field names in fieldsToLoad at time of sample
     * - a list of field names *added* to fieldsToLoad
     * - a list of field names *removed* from fieldsToLoad
     * - a list of all field names in response at time of sample
     * - a list of field names *added* to the response for the testing object
     * - a list of field names *removed* from the response for the testing object
     *
     * The key on the first level array is the time the sample was taken.
     *
     * @param Module $module
     * @return array[]
     */
    protected function getModuleHistory(Module $module)
    {
        $historyFile = $this->getHistoryFilename($module);
        if (!file_exists($historyFile)) {
            return [];
        }
        return json_decode(file_get_contents($historyFile), true);
    }

    protected function getHistoryFilename(Module $module)
    {
        $historyFilesFolder = 'fileadmin/api_samples/';
        return GeneralUtility::getFileAbsFileName($historyFilesFolder) . $module->getModuleName() . '.json';
    }

    /**
     * Initialize system
     *
     * Creates Server and Module configuration if configured in
     * extension configuration. The array in:
     *
     * $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fourallportal']
     *
     * can contain an array of servers and modules, e.g.:
     *
     * · [
     * ·   'default' => [
     * ·     'domain' => '',
     * ·     'customerName' => '',
     * ·     'username' => '',
     * ·     'password' => '',
     * ·     'active' => 1,
     * ·     'modules' => [
     * ·       'module_name' => [
     * ·         'connectorName' => '',
     * ·         'mappingClass' => '',
     * ·         'shellPath' => '',
     * ·         'falStorage' => '',
     * ·         'storagePid' => '',
     * ·       ],
     * ·     ],
     * ·   ],
     * · ]
     *
     * Note that the module properties may differ depending on which
     * mapping class the module uses, and that the server name does
     * not get used - it is only there to identify the entry in your
     * configuration file.
     *
     * @param bool $fail If TRUE, any connectivity test failure will cause the command to exit with failure.
     */
    public function initializeCommand($fail = false)
    {
        $settings = GeneralUtility::removeDotsFromTS((array) unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fourallportal']));
        if (isset($settings['servers'])) {
            foreach ($settings['servers'] as $server) {
                $currentServer = $this->serverRepository->findOneByDomain($server['domain']);
                if (!$currentServer) {
                    $this->response->appendContent('Creating new server for ' . $server['domain'] . PHP_EOL);
                    $currentServer = new Server();
                    $this->serverRepository->add($currentServer);
                } else {
                    $this->response->appendContent('Updating configuration for ' . $server['domain'] . PHP_EOL);
                    $this->serverRepository->update($currentServer);
                }

                if ($server['username']) {
                    $currentServer->setUsername($server['username']);
                    $this->response->appendContent('* Username: ' . $server['username'] . PHP_EOL);
                }

                if ($server['password']) {
                    $currentServer->setPassword($server['password']);
                    $this->response->appendContent('* Password: ' . $server['password'] . PHP_EOL);
                }

                $currentServer->setActive((bool) $server['active']);
                $this->response->appendContent('* Active: ' . $server['active'] . PHP_EOL);

                $currentServer->setCustomerName($server['customerName']);
                $this->response->appendContent('* Customer name: ' . $server['customerName'] . PHP_EOL);

                $currentServer->setDomain($server['domain']);

                $this->response->appendContent('* Testing connectivity... ');
                $this->response->send();
                $this->response->setContent('');

                try {
                    $currentServer->getClient()->login();
                    $this->response->appendContent('OKAY!');
                } catch (\Exception $error) {
                    $this->response->appendContent('ERROR! ' . $error->getMessage());
                    if ($fail) {
                        $this->response->setExitCode(1);
                    }
                }
                $this->response->appendContent(PHP_EOL);

                foreach ($server['modules'] as $moduleName => $moduleProperties) {
                    $module = $this->ensureServerHasModule($currentServer, $moduleName, $moduleProperties);
                    $module->setServer($currentServer);

                    $this->response->appendContent('* Testing connectivity... ');
                    $this->response->send();
                    $this->response->setContent('');

                    try {
                        $module->getModuleConfiguration();
                        $this->response->appendContent('OKAY!');
                    } catch (\Exception $error) {
                        $this->response->appendContent('ERROR! ' . $error->getMessage());
                        if ($fail) {
                            $this->response->setExitCode(1);
                        }
                    }
                    $this->response->appendContent(PHP_EOL);
                }
            }
        }
        $this->objectManager->get(PersistenceManager::class)->persistAll();
    }

    /**
     * @param Server $server
     * @param string $moduleName
     * @param array $moduleProperties
     * @return Module
     */
    protected function ensureServerHasModule(Server $server, $moduleName, array $moduleProperties)
    {
        $currentModule = new Module();
        $foundModule = false;
        foreach ($server->getModules() as $module) {
            if ($module->getModuleName() === $moduleName) {
                $foundModule = true;
                $currentModule = $module;
                break;
            }
        }
        if (!$foundModule) {
            $this->response->appendContent('Adding new module for ' . $moduleName . PHP_EOL);
        } else {
            $this->response->appendContent('Updating existing module configuration for ' . $moduleName . PHP_EOL);
        }

        $currentModule->setModuleName($moduleName);

        $currentModule->setConnectorName($moduleProperties['connectorName']);
        $this->response->appendContent('* Connector name: ' . $moduleProperties['connectorName'] . PHP_EOL);

        $currentModule->setMappingClass($moduleProperties['mappingClass']);
        $this->response->appendContent('* Mapping class: ' . $moduleProperties['mappingClass'] . PHP_EOL);

        $currentModule->setEnableDynamicModel($moduleProperties['enableDynamicModel'] ?? false);
        $this->response->appendContent('* Dynamic: ' . $moduleProperties['enableDynamicModel'] . PHP_EOL);

        if ($moduleProperties['shellPath'] ?? false) {
            $currentModule->setShellPath($moduleProperties['shellPath']);
            $this->response->appendContent('* Shell path: ' . $moduleProperties['shellPath'] . PHP_EOL);
        }

        if ($moduleProperties['falStorage'] ?? false) {
            $currentModule->setFalStorage((int) $moduleProperties['falStorage']);
            $this->response->appendContent('* FAL storage: ' . $moduleProperties['falStorage'] . PHP_EOL);
        }

        if ($moduleProperties['storagePid'] ?? false) {
            $currentModule->setStoragePid((int) $moduleProperties['storagePid']);
            $this->response->appendContent('* Storage PID: ' . $moduleProperties['storagePid'] . PHP_EOL);
        }

        if ($currentModule->getUid()) {
            $this->moduleRepository->update($currentModule);
        } else {
            $this->moduleRepository->add($currentModule);
            $server->getModules()->attach($currentModule);
        }
        return $currentModule;
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
     * @param bool $strict If TRUE, generates strict PHP code
     * @param bool $readOnly If TRUE, generates TCA fields as read-only
     */
    public function generateCommand($entityClassName = null, $strict = false, $readOnly = false)
    {
        $this->generateSqlSchemaCommand();
        $this->generateTableConfigurationCommand($entityClassName, $readOnly);
        $this->generateAbstractModelClassCommand($entityClassName, $strict);
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
     * @param bool $readOnly If TRUE, generates TCA fields as read-only
     */
    public function generateTableConfigurationCommand($entityClassName = null, $readOnly = false)
    {
        foreach ($this->getEntityClassNames($entityClassName) as $entityClassName) {
            $tca = DynamicModelGenerator::generateAutomaticTableConfigurationForModelClassName($entityClassName, $readOnly);
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
     * @param bool $strict If TRUE, generates strict PHP code
     */
    public function generateAbstractModelClassCommand($entityClassName = null, $strict = false)
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
            $sourceCode = $dynamicModelGenerator->generateAbstractModelForModule($module, $strict);
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
     * Unlock sync
     *
     * Removes a (stale) lock.
     *
     * @param int $requiredAge Number of seconds, required minimum age of the lock file before removal will be allowed.
     */
    public function unlockCommand($requiredAge = 0)
    {
        $this->eventExecutionService->setResponse($this->response);
        $this->eventExecutionService->unlock($requiredAge);
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
    public function replayCommand($events = 1, $module = null, $objectId = null)
    {
        $this->eventExecutionService->setResponse($this->response);
        $this->eventExecutionService->replay($events, $module, $objectId);
    }

    /**
     * Sync data
     *
     * Execute this to synchronise events from the PIM API.
     *
     * @param bool $sync Set to "1" to sync events (starting from last received event). If execute=true will happen before executing.
     * @param bool $fullSync Set to "1" to trigger a full sync
     * @param string $module If passed can be used to only sync one module, using the module or connector name it has in 4AP.
     * @param string $exclude Exclude a list of modules from processing (CSV string module names)
     * @param bool $force If set, forces the sync to run regardless of lock and will neither lock nor unlock the task
     * @param bool $execute If true, also executes events after receiving (syncing) events
     * @param int $maxEvents Maximum number of events to process. Default is unlimited. Affects only the number of events being executed, if sync is enabled will still sync all.
     * @param int $maxTime Maximum number of seconds that the sync is allowed to run, once expired, will require a new execution to continue
     * @param int $maxThreads Maximum number of concurrent threads which are allowed to execute events. Ignored if sync=true.
     */
    public function syncCommand(
        $sync = false,
        $fullSync = false,
        $module = null,
        $exclude = null,
        $force = false,
        $execute = false,
        $maxEvents = 0,
        $maxTime = 0,
        $maxThreads = 4
    ) {
        if (!$sync) {
            // We are executing only, not syncing. Check number of currently running threads - if no more threads are
            // allowed, exit early.
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_fourallportal_domain_model_event');
            $query = $queryBuilder->select('uid')->from('tx_fourallportal_domain_model_event')->where($queryBuilder->expr()->eq('processing', 1));
            $currentThreadCount = $query->execute()->rowCount();
            if ($currentThreadCount >= $maxThreads) {
                return;
            }
        }
        if (!$force && $sync) {
            try {
                $this->eventExecutionService->lock();
            } catch (\Exception $error) {
                $this->response->setContent('Cannot acquire lock - exiting without error' . PHP_EOL);
                $this->response->send();
                return;
            }
        }

        $syncParameters = GeneralUtility::makeInstance(SyncParameters::class)
            ->setSync((bool)$sync)
            ->setFullSync((bool)$fullSync)
            ->setModule($module)
            ->setExclude($exclude)
            ->setForce($force)
            ->setExecute($execute)
            ->setEventLimit((int)$maxEvents)
            ->setTimeLimit((int)$maxTime);

        $this->eventExecutionService->setResponse($this->response);
        $this->eventExecutionService->sync($syncParameters);

        if (!$force && $sync) {
            $this->eventExecutionService->unlock();
        }
    }
}
