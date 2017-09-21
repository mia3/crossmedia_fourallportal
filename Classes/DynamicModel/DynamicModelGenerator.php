<?php
namespace Crossmedia\Fourallportal\DynamicModel;

use Crossmedia\Fourallportal\Domain\Model\ComplexType;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Domain\Repository\ServerRepository;
use Crossmedia\Fourallportal\Mapping\MappingRegister;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Class DynamicModelGenerator
 *
 * Generates abstract model classes based on an input
 * list of properties. Also handles dynamic schema generation
 * based on the same input.
 */
class DynamicModelGenerator
{
    /**
     * @return array
     */
    public function addSchemasForAllModules(array $sqlString)
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        $manyToManyRelations = [];

        foreach ($this->getAllConfiguredModules() as $module) {

            $propertyConfigurations = $this->getPropertyConfigurationFromConnector($module);
            if (empty($propertyConfigurations)) {
                continue;
            }

            $tableName = $objectManager->get(DataMapper::class)->getDataMap($module->getMapper()->getEntityClassName())->getTableName();

            $lines = [];
            foreach ($propertyConfigurations as $propertyConfiguration) {
                $lines[] = $propertyConfiguration['column'] . ' ' . $propertyConfiguration['schema'];
                if ($propertyConfiguration['tca']['config']['MM']) {
                    $manyToManyRelations[] = $propertyConfiguration;
                }
            }
            $sqlString[] = 'CREATE TABLE ' . $tableName . ' (' . PHP_EOL . implode(',' . PHP_EOL, $lines) . PHP_EOL . ');';
        }

        // Process all queued MM table creations
        foreach ($manyToManyRelations as $relationPropertyConfiguration) {

        }

        return [$sqlString];
    }

    /**
     * @param string $modelClassName
     * @return array
     */
    public static function generateTableConfigurationForModuleIdentifiedByModelClassName($modelClassName)
    {
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cacheManager->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);

        $columns = [];
        foreach (GeneralUtility::makeInstance(static::class)->getAllConfiguredModules() as $module) {
            if ($module->getMapper()->getEntityClassName() === $modelClassName) {
                $propertyConfigurations = (new static())->getPropertyConfigurationFromConnector($module);
                foreach ($propertyConfigurations as $propertyConfiguration) {
                    $columns[$propertyConfiguration['column']] = [
                        'label' => 'Automatic model field: ' . $propertyConfiguration['column'],
                        'exclude' => true,
                        'config' => $propertyConfiguration['config']
                    ];
                }
            }
        }
        return $columns;
    }

    /**
     * @return void
     */
    public function regenerateModelsAfterCacheFlush()
    {
        $this->generateAbstractModelsForAllModules();
    }

    /**
     * @param boolean $safeMode
     * @return void
     */
    public function generateAbstractModelsForAllModules($safeMode = false)
    {
        // Loop 1: generate fallbacks
        foreach ($this->getAllConfiguredModules() as $module) {
            $this->generateAbstractModelForModule($module, true);
        }
        if ($safeMode === false) {
            // Loop 2: generate actual classes, which require the presence of the fallback in order for the data map to work
            foreach ($this->getAllConfiguredModules() as $module) {
                $this->generateAbstractModelForModule($module);
            }
        }
    }

    /**
     * Generates one abstract model class based on properties
     * of the Module. Generates the class in two steps:
     *
     * 1. A completely safe fallback that has none of the
     *    properties read from the remote API, generated
     *    before any of the properties are analysed. If a
     *    property can't be converted to safe PHP/SQL then
     *    the generation exists and the fallback is kept.
     * 2. The actual class with all properties as read from
     *    the remote API.
     *
     * The two-step generation ensures that even if the second
     * step with dynamic properties fail (perhaps due to logic
     * errors or connectivity issues) a safe fallback is
     * guaranteed to exist and be loadable through the
     * loadAbstractClass() method on this class.
     *
     * @param Module $module
     * @param boolean $asFallback
     * @return void
     */
    public function generateAbstractModelForModule(Module $module, $asFallback = false)
    {
        $repository = $module->getMapper()->getObjectRepository();

        $entityClassName = substr(str_replace('\\Domain\\Repository\\', '\\Domain\\Model\\', get_class($repository)), 0, -10);
        $entityClassNameParts = explode('\\', $entityClassName);
        $classNameWithoutNamespace = array_pop($entityClassNameParts);
        $abstractModelClassName = implode('\\', $entityClassNameParts) . '\\Abstract' . $classNameWithoutNamespace;

        if ($asFallback) {
            // Phase 1: create a safe fallback that has none of the properties returned from the API.
            // This class is generated at a time in the process where errors based on remote API data
            // have not yet been raised - as long as the local code base is intact, this class can be
            // generated and trusted.
            $this->generateCachedClassFile($abstractModelClassName, AbstractEntity::class, [], sha1($abstractModelClassName) . '_fallback');

        } else {
            // Phase 2: the more risky dynamic model with properties read from the remote API. If any
            // properties returned from the API are unsupported or otherwise can't be expressed as safe
            // PHP/SQL representations, either errors will be raised or the remaining safe properties
            // will be written - depending on the context of the TYPO3 site (Development = errors thrown)
            $propertyConfiguration = $this->getPropertyConfigurationFromConnector($module);
            $this->generateCachedClassFile($abstractModelClassName, AbstractEntity::class, $propertyConfiguration);
        }
    }

    /**
     * @param string $className
     * @param boolean $safe
     * @throws \RuntimeException
     */
    public static function loadAbstractClass($className, $safe = true)
    {
        // The first check allows autoload to happen. This is done in order to make it possible to
        // opt out from the dynamic models by simply adding your own base class which uses this name.
        if (class_exists($className)) {
            return;
        }
        $identifier = sha1($className);
        $cache = static::getGeneratedClassCache();
        $cache->requireOnce($identifier);

        if (!class_exists($className, false)) {
            // We will attempt to recreate *all* model classes now since one missing class very likely
            // means all classes are missing. However, this may not be possible to do at the time when
            // this code is executed in the runtime - so we catch and suppress errors and instead throw
            // a RuntimeException asking the administrator to manually regenerate the classes. Note that
            // when this exception is thrown, simply calling another piece of code may cause the classes
            // to be regenerated correctly.
            try {
                GeneralUtility::makeInstance(static::class)->generateAbstractModelsForAllModules($safe);
                $cache->requireOnce($identifier . '_fallback');
            } catch (\RuntimeException $error) {
                // Suppressed, see above.
            }
        }

        if (!class_exists($className, false)) {
            // Attempt to load the fallback class which should be completely safe and always present even if
            // the generated class with properties from the remote API could not be loaded. Check the TYPO3
            // application context first of all - if we are in Development context, throw an error instead of
            // silently allowing the base class to load. This is done in order to protect Production systems
            // from potentially uncaught exceptions or complete failures when trying to use the models.
            // Using the model classes is then possible, but none of the dynamic properties can be retrieved.
            if (Bootstrap::getInstance()->getApplicationContext()->isDevelopment()) {
                throw new \RuntimeException(
                    sprintf(
                        'Attempting to load the dynamically generated class "%s" caused the fallback class to be ' .
                        'resolved. Since your TYPO3 site is in Development application context you see this error ' .
                        'to inform you that there is a possible lack of support for the returned properties that you ' .
                        'should address in the code before you let it deploy to production.',
                        $className
                    )
                );
            }

            try {
                $cache->requireOnce($identifier . '_fallback');
            } catch (\RuntimeException $error) {
                // Suppressed; if even the fallback class can't load, the standard reason below will be given.
            }
        }

        // Final check - if the class wasn't loaded by now, that's a fatal error.
        if (!class_exists($className, false)) {
            throw new \RuntimeException(
                sprintf('Dynamic Fourall class "%s" could not be loaded, please regenerate classes!', $className)
            );
        }
    }

    /**
     * Return an array of for example:
     *
     *     ['propertyNameInModel' => ['type' => 'string', 'column' => 'sql_column_name', 'schema' => 'varchar(255) default \'\' not null', 'config' => $tcaConfigArray]
     *
     * - one for each of the properties returned from the remote API.
     *
     * @param Module $module
     * @return array
     */
    protected function getPropertyConfigurationFromConnector(Module $module)
    {
        $properties = [];
        $map = MappingRegister::resolvePropertyMapForMapper($module->getMappingClass());
        $moduleConfiguration = $module->getModuleConfiguration();
        $connectorConfiguration = $module->getConnectorConfiguration();

        $fieldsAndRelations = array_replace(
            array_intersect_assoc(
                $moduleConfiguration['field_conf'],
                $connectorConfiguration['fieldsToLoad']
            ),
            (array) $moduleConfiguration['relation_conf']
        );

        foreach ($fieldsAndRelations as $originalName => $fieldConfiguration) {

            try {
                if (isset($map[$originalName])) {
                    // This property is explicitly mapped in the mapping array, indicating it is manually
                    // added to the sub-class of the abstract class we are generating, thus needs to be skipped.
                    continue;
                } elseif (!($map[$originalName] ?? false) && (preg_match('/[^a-z0-9_]/i', $originalName) || preg_match('/[^a-z]/i', $originalName{0}))) {
                    // Property uses a name that is impossible to express as SQL type and it was NOT defined in
                    // the property map for the class. This must yield an exception.
                    throw new \RuntimeException(
                        sprintf(
                            'Property "%s" should map to "%s" but the property name contains invalid characters and is ' .
                            'not configured in the manual property map. To map this property - which you must do even ' .
                            'if the property should just be ignored by mapping it to "false" as target column - please ' .
                            'add it to the property map for the model "%s"',
                            $originalName,
                            GeneralUtility::underscoredToLowerCamelCase($originalName),
                            $module->getMapper()->getEntityClassName()
                        )
                    );
                }
                list ($type, $schema, $tca) = $this->guessLocalTypesFromRemoteField($fieldConfiguration);
                $properties[GeneralUtility::underscoredToLowerCamelCase($originalName)] = [
                    'column' => $originalName,
                    'type' => $type,
                    'schema' => $schema,
                    'config' => $tca
                ];
            } catch (\RuntimeException $error) {
                if (Bootstrap::getInstance()->getApplicationContext()->isDevelopment()) {
                    throw $error;
                }
            }
        }

        return $properties;
    }

    /**
     * @param array $fieldConfiguration
     * @return array
     */
    protected function guessLocalTypesFromRemoteField(array $fieldConfiguration)
    {
        $textFieldTypes = ['CEText', 'MAMString', 'XMPString'];
        if ($fieldConfiguration['fulltext'] || in_array($fieldConfiguration['type'], $textFieldTypes)) {
            // Shortcut: any fulltext/text typed fields will be "string" in class property and "text" in SQL
            return ['string', 'text', ['type' => 'text']];
        }

        $dataType = $sqlType = null;
        $tca = [
            'type' => 'passthrough'
        ];

        switch ($fieldConfiguration['type']) {
            case 'CEVarchar':
                $dataType = 'string';
                $sqlType = sprintf(
                    'varchar(%d) %s',
                    $fieldConfiguration['length'],
                    $fieldConfiguration['notNull'] ? 'NOT NULL' : 'default \'\''
                );
                $tca = [
                    'type' => 'input',
                    'size' => $fieldConfiguration['length']
                ];
                break;
            case 'CEDate':
                $dataType = '\\DateTime';
                $sqlType = 'int(11) default 0 NOT NULL';
                $tca = [
                    'type' => 'input'
                ];
                break;
            case 'CEBoolean':
                $dataType = 'boolean';
                $sqlType = 'int(1) default 0 NOT NULL';
                $tca = [
                    'type' => 'check'
                ];
                break;
            case 'CEDouble':
                $dataType = 'float';
                $sqlType = 'double(10,6) default 0.0 NOT NULL';
                $tca = [
                    'type' => 'input'
                ];
                break;
            case 'CETimestamp':
            case 'CEInteger':
            case 'MAMNumber':
            case 'XMPNumber':
                $dataType = 'integer';
                $sqlType = 'int(11) default 0 NOT NULL';
                $tca = [
                    'type' => 'input',
                    'eval' => 'trim,int'
                ];
                break;
            case 'MAMList':
            case 'CEVarcharList':
                $dataType = 'array';
                $sqlType = 'text';
                break;
            case 'CEId':
            case 'CEIdList':
            case 'CEExternalId':
            case 'CEExternalIdList':
            case 'MANY_TO_MANY':
            case 'ONE_TO_ONE':
            case 'ONE_TO_MANY':
            case 'MANY_TO_ONE':
            case 'FIELD_LINK':
                #$tca = $this->determineTableConfigurationForRelation($fieldConfiguration);
                $tca = ['type' => 'passthrough'];
                $dataType = 'integer';
                $sqlType = 'int(11) default 0 NOT NULL';
                break;
            default:
                break;
        }

        if (!$dataType && !$sqlType) {
            // The field was not of a standard type and is most likely a "ComplexType". Make sure the necessary
            // ComplexType template exists, purely as validation. ComplexType is always saved as a 1:1 relation
            // so the data- and SQL types are always the same. If the ComplexType cannot be found, this fact is
            // either thrown as Exception (Development context) or silently ignored (Production context). When
            // ignored, the field does not get added to SQL, TCA or model properties.
            $this->validatePresenceOfComplexType($fieldConfiguration);
            $dataType = '\\' . ComplexType::class;
            $sqlType = 'int(11) default 0 NOT NULL';
        }

        return [
            $dataType,
            $sqlType,
            $tca
        ];
    }

    /**
     * Attempts to determine a valid TCA configuration expressing
     * a relationship (which is required for TYPO3 to handle the
     * relation correctly).
     *
     * A valid relation is determined by the presence of a connector
     * in the TYPO3 system which handles the module associated with
     * the relation. If such a connector exists, it contains the
     * information necessary to determine which object the relation
     * points to and which relationship type it uses - and thus it
     * becomes possible to create the TCA that is required.
     *
     * If a relation is considered invalid due to a missing connector
     * an Exception is thrown (and the error is either reported or
     * ignored and the property skipped, depending on TYPO3 context).
     *
     * @param array $fieldConfiguration
     * @throws \RuntimeException
     * @return array
     */
    protected function determineTableConfigurationForRelation(array $fieldConfiguration)
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $dataMapper = $objectManager->get(DataMapper::class);
        $modules = $this->getAllConfiguredModules();

        try {
            $tableNameParent = null;
            if ($fieldConfiguration['parent'] ?? false) {
                $this->validatePresenceOfConfiguredConnectorForModule($fieldConfiguration['parent']);
                $tableNameParent = $dataMapper->getDataMap($modules[$fieldConfiguration['parent']]->getMapper()->getEntityClassName())->getTableName();
            }
        } catch (\RuntimeException $error) {
            throw new \RuntimeException(
                sprintf(
                    'Field "%s" uses module "%s" as parent in a relation, but the parent module is unknown to TYPO3. ' .
                    'Please either configure a connector that uses the module, create a manual mapping for the field ' .
                    'or configure the field as ignored by the mapper.',
                    $fieldConfiguration['name'],
                    $fieldConfiguration['parent']
                )
            );
        }

        try {
            $tableNameChild = null;
            if ($fieldConfiguration['child'] ?? false) {
                $this->validatePresenceOfConfiguredConnectorForModule($fieldConfiguration['child']);
                $tableNameChild = $dataMapper->getDataMap($modules[$fieldConfiguration['child']]->getMapper()->getEntityClassName())->getTableName();
            }
        } catch (\RuntimeException $error) {
            throw new \RuntimeException(
                sprintf(
                    'Field "%s" uses module "%s" as child in a relation, but the child module is unknown to TYPO3. ' .
                    'Please either configure a connector that uses the module, create a manual mapping for the field ' .
                    'or configure the field as ignored by the mapper.',
                    $fieldConfiguration['name'],
                    $fieldConfiguration['child']
                )
            );
        }


        $tca = [
            'type' => 'select',
            'foreign_table' => $tableNameParent
        ];
        switch ($fieldConfiguration['type']) {
            case 'MANY_TO_MANY':
                break;
            case 'ONE_TO_ONE':
                break;
            case 'ONE_TO_MANY':
                break;
            case 'MANY_TO_ONE':
                break;
        }
        return $tca;
    }

    /**
     * @param string $moduleName
     * @throws \RuntimeException
     */
    protected function validatePresenceOfConfiguredConnectorForModule($moduleName)
    {
        if (empty($this->getAllConfiguredModules()[$moduleName])) {
            throw new \RuntimeException(sprintf('Module "%s" is unknown to TYPO3, make sure it is configured!', $moduleName));
        }
    }

    /**
     * Validate that a ComplexType exists in the system and can
     * be requested via the API. If the type cannot be found an
     * exception gets thrown.
     *
     * @param array $fieldConfiguration
     * @return void
     */
    protected function validatePresenceOfComplexType(array $fieldConfiguration)
    {
        ComplexTypeFactory::getPreparedComplexType($fieldConfiguration['type'], $fieldConfiguration);
    }

    /**
     * Generates the actual class file using templates.
     *
     * Actually generates two different classes which use
     * the same name, but have different priorities:
     *
     * 1. A completely safe fallback that has none of the
     *    properties read from the remote API.
     * 2. The actual class with all properties as read from
     *    the remote API.
     *
     * The two-step generation ensures that even if the second
     * step with dynamic properties fail (perhaps due to logic
     * errors or connectivity issues) a safe fallback is
     * guaranteed to exist and be loadable. The second step
     * simply overwrites the generated fallback if no
     *
     * @param string $className
     * @param string $parentClass
     * @param array $propertyConfiguration
     * @param string $identifier
     * @return void
     */
    protected function generateCachedClassFile($className, $parentClass, array $propertyConfiguration, $identifier = null)
    {
        $propertyTemplate = <<< TEMPLATE

    /**
     * @var %s
     */
    protected \$%s = %s;
    
    public function get%s()
    {
        return \$this->%s;
    }
    
    /**
     * @param %s %s
     */
    public function set%s(%s)
    {
        \$this->%s = %s;
    }

TEMPLATE;

        $functionsAndProperties = '';
        foreach ($propertyConfiguration as $propertyName => $property) {
            $upperCasePropertyName = ucfirst($propertyName);
            $functionsAndProperties .= sprintf(
                $propertyTemplate,
                $property['type'],
                $propertyName,
                var_export($property['default'], true),
                $upperCasePropertyName,
                $propertyName,
                $property['type'],
                '$' . $propertyName,
                $upperCasePropertyName,
                '$' . $propertyName,
                $propertyName,
                '$' . $propertyName
            );
        }

        $classTemplate = <<< TEMPLATE
namespace %s;

class %s extends %s
{
    %s
}

TEMPLATE;
        $classNameParts = explode('\\', $className);
        $classShortName = array_pop($classNameParts);
        $namespace = implode('\\', $classNameParts);

        $classSourceCode = sprintf(
            $classTemplate,
            $namespace,
            $classShortName,
            '\\' . ltrim($parentClass, '\\'),
            $functionsAndProperties
        );

        $identifier = $identifier ?: sha1($className);
        static::getGeneratedClassCache()->set($identifier, $classSourceCode);
    }

    /**
     * @return PhpFrontend
     */
    protected static function getGeneratedClassCache()
    {
        return GeneralUtility::makeInstance(CacheManager::class)->getCache('fourallportal_classes');
    }

    /**
     * @return Module[]
     */
    protected function getAllConfiguredModules()
    {
        static $configuredModules = [];
        if (!empty($configuredModules)) {
            return $configuredModules;
        }
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        $requirePersist = false;

        /** @var Server[] $servers */
        $servers = $objectManager->get(ServerRepository::class)->findByActive(true);
        foreach ($servers as $server) {
            /** @var Module[] $modules */
            $modules = $server->getModules();
            foreach ($modules as $module) {
                if (empty($module->getModuleName())) {
                    $connectorConfiguration = $server->getClient()->getConnectorConfig($module->getConnectorName());
                    $module->setModuleName($connectorConfiguration['moduleConfig']['module_name']);
                    $module->update();
                    $requirePersist = true;
                }
                $configuredModules[$module->getModuleName()] = $module;
            }
        }

        if ($requirePersist) {
            GeneralUtility::makeInstance(ObjectManager::class)->get(PersistenceManager::class)->persistAll();
        }

        return $configuredModules;
    }
}
