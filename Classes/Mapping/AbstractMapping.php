<?php
namespace Crossmedia\Fourallportal\Mapping;

use Crossmedia\Fourallportal\Domain\Model\DimensionMapping;
use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Service\ApiClient;
use Crossmedia\Fourallportal\TypeConverter\PimBasedTypeConverterInterface;
use Crossmedia\Fourallportal\ValueReader\ResponseDataFieldValueReader;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;
use TYPO3\CMS\Extbase\Property\TypeConverter\PersistentObjectConverter;
use TYPO3\CMS\Extbase\Reflection\Exception\PropertyNotAccessibleException;
use TYPO3\CMS\Extbase\Reflection\MethodReflection;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Extbase\Reflection\PropertyReflection;
use TYPO3\CMS\Extbase\Validation\Error;

abstract class AbstractMapping implements MappingInterface
{
    /**
     * @var string
     */
    protected $repositoryClassName;

    /**
     * @param array $data
     * @param Event $event
     * @return bool
     */
    public function import(array $data, Event $event)
    {
        $repository = $this->getObjectRepository();
        $objectId = $event->getObjectId();
        $query = $repository->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('remoteId', $objectId));
        $object = $query->execute()->current();
        $deferAfterProcessing = false;

        switch ($event->getEventType()) {
            case 'delete':
                if (!$object) {
                    // push back event.

                    return;
                }
                $this->removeObjectFromRepository($object);
                unset($object);
                break;
            case 'update':
            case 'create':
                $deferAfterProcessing = $this->importObjectWithDimensionMappings($data, $object, $event);
                break;
            default:
                throw new \RuntimeException('Unknown event type: ' . $event->getEventType());
        }

        if (isset($object)) {
            $this->processRelationships($object, $data, $event);
        }

        return $deferAfterProcessing;
    }

    protected function removeObject(DomainObjectInterface $object)
    {
        GeneralUtility::makeInstance(ObjectManager::class)->get(PersistenceManager::class)->remove($object);
    }

    protected function removeObjectFromRepository(DomainObjectInterface $object)
    {
        $this->getObjectRepository()->remove($object);
    }

    /**
     * @param array $data
     * @param AbstractEntity $object
     * @param Module $module
     * @param DimensionMapping|null $dimensionMapping
     * @return bool
     */
    protected function mapPropertiesFromDataToObject(array $data, $object, Module $module, DimensionMapping $dimensionMapping = null)
    {
        if (!$data['result']) {
            return true;
        }
        $map = MappingRegister::resolvePropertyMapForMapper(static::class);
        $properties = $data['result'][0]['properties'];
        $responseValueReader = new ResponseDataFieldValueReader();
        $properties = $this->addMissingNullProperties($properties, $module);
        $mappingProblemsOccurred = false;
        foreach ($properties as $importedName => $propertyValue) {
            if (($map[$importedName] ?? null) === false) {
                continue;
            }
            try {
                $propertyValue = $responseValueReader->readResponseDataField($data['result'][0], $importedName, $dimensionMapping);
                $customSetter = MappingRegister::resolvePropertyValueSetter(static::class, $importedName);
                if ($customSetter) {
                    $customSetter->setValueOnObject($propertyValue, $importedName, $data, $object, $module, $this);
                } else {
                    $targetPropertyName = isset($map[$importedName]) ? $map[$importedName] : GeneralUtility::underscoredToLowerCamelCase($importedName);
                    $propertyMappingProblemsOccurred = $this->mapPropertyValueToObject($targetPropertyName, $propertyValue, $object);
                    $mappingProblemsOccurred = $mappingProblemsOccurred ?: $propertyMappingProblemsOccurred;
                }
            } catch (PropertyNotAccessibleException $error) {
                $this->logProblem($error->getMessage());
                $mappingProblemsOccurred = true;
            }
        }
        return $mappingProblemsOccurred;
    }

    /**
     * @param string $propertyName
     * @param mixed $propertyValue
     * @param AbstractEntity $object
     * @return bool
     */
    protected function mapPropertyValueToObject($propertyName, $propertyValue, $object)
    {
        if (!property_exists(get_class($object), $propertyName)) {
            return false;
        }
        $currentPropertyValue = ObjectAccess::getProperty($object, $propertyName);
        // We need to check if the current value is an instance of the special FileReference proxy, which if it is, needs
        // to be explicitly removed from the repository so the mapping can create fresh instances.
        if ($currentPropertyValue instanceof FileReference) {
            // We unset, but do not return, so that:
            // 1) if the value is null, the property gets nulled and we return just below here
            // 2) if it is not, a new reference will be created and the value overridden in the end of the function.
            $this->removeObject($currentPropertyValue);
            $this->persist();
        }

        if ($propertyValue === null && reset((new \ReflectionMethod(get_class($object), 'set' . ucfirst($propertyName)))->getParameters())->allowsNull()) {
            ObjectAccess::setProperty($object, $propertyName, null);
            return false;
        }
        $configuration = new PropertyMappingConfiguration();

        $configuration->allowAllProperties();
        $configuration->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED, true);
        $configuration->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_MODIFICATION_ALLOWED, true);
        $configuration->setTypeConverterOption(DateTimeConverter::class, DateTimeConverter::CONFIGURATION_DATE_FORMAT, 'Y#m#d\\TH#i#s+');


        $propertyMapper = $this->getAccessiblePropertyMapper();
        $targetType = $this->determineDataTypeForProperty($propertyName, $object);
        if (strpos($targetType, '<')) {
            $childType = substr($targetType, strpos($targetType, '<') + 1, -1);
            $childType = trim($childType, '\\');
            $objectStorage = ObjectAccess::getProperty($object, $propertyName) ?? new ObjectStorage();
            // Step one is to detach all currently related objects. Please note that $objectStorage->removeAll($objectStorage)
            // does not work due to array pointer reset issues with Iterators. The only functioning way is to iterate and
            // detach all, one by one, as below. Conversion to array is essential!
            foreach ($objectStorage->toArray() as $item) {
                $objectStorage->detach($item);

                // In addition, we need to check if the object being removed is the well-known proxy entity from Extbase
                // that creates relations between Extbase entities and `sys_file` objects which are not Extbase-based.
                // If the object being removed is such a reference, the object itself must also be removed.
                // NB: This must not be done for normal entities and any third-party integrations with this extension
                // must manually perform such removals in an override for this method, *BEFORE* calling the original method.
                if ($item instanceof FileReference) {
                    $this->removeObject($item);
                    $this->persist();
                }
            }

            foreach ((array) $propertyValue as $identifier) {
                if (!$identifier) {
                    continue;
                }
                $typeConverter = $propertyMapper->findTypeConverter($identifier, $childType, $configuration);
                if ($typeConverter instanceof PimBasedTypeConverterInterface) {
                    $typeConverter->setParentObjectAndProperty($object, $propertyName);
                }
                $child = $typeConverter->convertFrom($identifier, $childType, [], $configuration);

                if ($child instanceof Error) {
                    // For whatever reason, property validators will return a validation error rather than throw an exception.
                    // We therefore need to check this, log the problem, and skip the property.
                    $this->logProblem('Mapping error when mapping property ' . $propertyName . ' on ' . get_class($object) . ':' .  $object->getRemoteId() . ': ' . $child->getMessage());
                    $child = null;
                }

                if (!$child) {
                    $this->logProblem('Child of type ' . $childType . ' identified by ' . $identifier . ' not found when mapping property ' . $propertyName . ' on ' . get_class($object) . ':' .  $object->getRemoteId());
                    $mappingProblemsOccurred = true;
                    continue;
                }
                if (!$objectStorage->contains($child)) {
                    $objectStorage->attach($child);
                }
            }
            $propertyValue = $objectStorage;
        } elseif ($propertyValue !== null) {
            $sourceType = $propertyMapper->determineSourceType($propertyValue);
            $targetType = trim($targetType, '\\?');
            if ($targetType !== $sourceType) {
                if ($targetType === 'string' && $sourceType === 'array') {
                    $propertyValue = json_encode($propertyValue, JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG);
                } else {
                    $typeConverter = $propertyMapper->findTypeConverter($propertyValue, $targetType, $configuration);
                    if ($typeConverter instanceof PimBasedTypeConverterInterface) {
                        $typeConverter->setParentObjectAndProperty($object, $propertyName);
                    }
                    $propertyValue = $typeConverter->convertFrom($propertyValue, $targetType, [], $configuration);
                }

                if ($propertyValue instanceof Error) {
                    // For whatever reason, property validators will return a validation error rather than throw an exception.
                    // We therefore need to check this, log the problem, and skip the property.
                    $message = 'Mapping error when mapping property ' . $propertyName . ' on ' . get_class($object) . ':' .  $object->getRemoteId() . ': ' . $propertyValue->getMessage();
                    $mappingProblemsOccurred = true;
                    $this->logProblem($message);
                    $propertyValue = null;
                }

                // Sanity filter: do not attempt to set Entity with setter if an instance is required but the value is null.
                if ((new \ReflectionMethod(get_class($object), 'set' . ucfirst($propertyName)))->getNumberOfRequiredParameters() === 1) {
                    if (is_null($propertyValue) && is_a($targetType, AbstractEntity::class, true)) {
                        return false;
                    }
                }
            }
        }

        $setOnObject = $object;
        $lastPropertyName = $propertyName;
        if (strpos($propertyName, '.') !== false) {
            $propertyPath = explode('.', $propertyName);
            $lastPropertyName = array_pop($propertyPath);
            foreach ($propertyPath as $currentPropertyName) {
                $setOnObject = ObjectAccess::getProperty($setOnObject, $currentPropertyName);
            }
        }

        ObjectAccess::setProperty($setOnObject, $lastPropertyName, $propertyValue);

        return $mappingProblemsOccurred;
    }

    /**
     * @param $propertyName
     * @param $object
     * @return string|false
     */
    protected function determineDataTypeForProperty($propertyName, $object)
    {
        if (property_exists(get_class($object), $propertyName)) {
            $property = new PropertyReflection($object, $propertyName);
            $varTags = $property->getTagValues('var');
            if (!empty($varTags)) {
                return strpos($varTags[0], ' ') !== false ? substr($varTags[0], 0, strpos($varTags[0], ' ')) : $varTags[0];
            }
        }

        if (method_exists(get_class($object), 'set' . ucfirst($propertyName))) {
            $method = new MethodReflection($object, 'set' . ucfirst($propertyName));
            $parameters = $method->getParameters();
            if ($parameters[0]->hasType()) {
                return (string) $parameters[0]->getType();
            }

            $varTags = $method->getTagValues('param');
            if (!empty($varTags)) {
                return reset(explode(' ', $varTags[0]));
            }
        }

        throw new \RuntimeException('Type of property ' . $propertyName . ' on ' . get_class($object) . ' could not be determined');
    }

    /**
     * @param $object
     * @param array $data
     * @param Event $event
     */
    protected function processRelationships($object, array $data, Event $event)
    {

    }

    /**
     * @return string
     */
    public function getEntityClassName()
    {
        return substr(str_replace('\\Domain\\Repository\\', '\\Domain\\Model\\', $this->repositoryClassName), 0, -10);
    }

    /**
     * @return AccessiblePropertyMapper
     */
    protected function getAccessiblePropertyMapper()
    {
        return GeneralUtility::makeInstance(ObjectManager::class)->get(AccessiblePropertyMapper::class);
    }

    /**
     * @return RepositoryInterface
     */
    public function getObjectRepository()
    {
        return GeneralUtility::makeInstance(ObjectManager::class)->get($this->repositoryClassName);
    }

    /**
     * @param ApiClient $client
     * @param Module $module
     * @param array $status
     * @return array
     */
    public function check(ApiClient $client, Module $module, array $status)
    {
        $messages = [];
        // Verify the local mapping configuration exists and points to correct properties
        $entityClass = $this->getEntityClassName();
        $map = MappingRegister::resolvePropertyMapForMapper(static::class);
        $messages['property_checks'] = '<h4>
                Property mapping checks
            </h4>';
        if (empty($map)) {
            $messages[] = sprintf(
                '<p class="text-warning">This connector has no mapping information - fields will be mapped 1:1 to properties on %s</p>',
                $entityClass
            );
        } else {
            $messages[] = '<ol>';
            foreach ($map as $sourcePropertyName => $destinationPropertyName) {
                if (!$destinationPropertyName) {
                    $messages[] = '<li class="text-warning">';
                    $messages[] = $sourcePropertyName;
                    $messages[] = ' is ignored!';
                    $messages[] = '</li>';
                    continue;
                }
                $propertyExists = property_exists($entityClass, $destinationPropertyName);
                if ($propertyExists) {
                    $messages[] = '<li class="text-success">';
                } else {
                    $messages[] = '<li class="text-danger">';
                }
                $messages[] = $sourcePropertyName;
                $messages[] = ' is manually mapped to ' . $entityClass . '->' . $destinationPropertyName;
                if (!$propertyExists) {
                    $status['class'] = 'warning';
                    $messages[] = sprintf(' - property does not exist, will cause errors if <strong>%s</strong> is included in data!', $sourcePropertyName);
                }
                $messages[] = '</li>';
            }
        }

        foreach ((new \ReflectionClass($entityClass))->getProperties() as $reflectionProperty) {
            $name = $reflectionProperty->getName();
            if (in_array($name, $map)) {
                continue;
            }
            $setterMethod = 'set' . ucfirst($name);
            if (method_exists($entityClass, $setterMethod)) {
                $messages[] = sprintf(
                    '<li><strong>%s</strong> will map to <strong>%s->%s</strong></li>',
                    GeneralUtility::camelCaseToLowerCaseUnderscored($name),
                    $reflectionProperty->getDeclaringClass()->getNamespaceName(),
                    $name
                );
            }
        }
        $messages[] = '</ol>';

        $status['description'] .= implode(chr(10), $messages);
        return $status;
    }

    /**
     * @param $properties
     * @param Module $module
     * @return mixed
     */
    protected function addMissingNullProperties($properties, Module $module)
    {
        $moduleConfiguration = $module->getModuleConfiguration();
        foreach ($moduleConfiguration['field_conf'] as $field) {
            if (!isset($properties[$field['name']])) {
                $value = '';
                if (isset($field['defaultValue'])) {
                    $value = $field['defaultValue'];
                } else {
                    switch ($field['type']) {
                        case 'CEVarchar':
                            $value = '';
                            break;
                        case 'MAMDate':
                        case 'CEDate':
                            $value = null;
                            break;
                        case 'MAMBoolean';
                        case 'CEBoolean':
                            $value = false;
                            break;
                        case 'CEDouble':
                            $value = 0.0;
                            break;
                        case 'CETimestamp':
                        case 'CEInteger':
                        case 'CELong':
                        case 'MAMNumber':
                        case 'XMPNumber':
                            $value = 0;
                            break;
                        case 'MAMList':
                        case 'CEVarcharList':
                        case 'FIELD_LINK':
                        case 'CEExternalIdList':
                        case 'CEIdList':
                        case 'MANY_TO_MANY':
                        case 'ONE_TO_MANY':
                        case 'MANY_TO_ONE':
                            $value = [];
                            break;
                        case 'CEId':
                        case 'CEExternalId':
                        case 'ONE_TO_ONE':
                            $value = null;
                            break;
                        default:
                            break;
                    }
                }
                $properties[$field['name']]['value'] = $value;
            }
        }

        return $properties;
    }

    /**
     * @param Event $event
     * @param null $existingRow
     * @return mixed
     */
    protected function createObject(Event $event, $existingRow = null)
    {
        $class = $this->getEntityClassName();
        $object = new $class();
        ObjectAccess::setProperty($object, 'remoteId', $event->getObjectId());
        ObjectAccess::setProperty($object, 'pid', $event->getModule()->getStoragePid());
        if (isset($existingRow['uid'])) {
            ObjectAccess::setProperty($object, 'uid', $existingRow['uid']);
        }
        $this->getObjectRepository()->add($object);
        $this->persist();
        return $object;
    }

    /**
     * Persists all objects pending for ORM
     *
     * @return void
     */
    protected function persist()
    {
        GeneralUtility::makeInstance(ObjectManager::class)->get(PersistenceManager::class)->persistAll();
    }

    public function getTableName() {

        $dataMapper = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper::class);
        return $dataMapper->getDataMap($this->getEntityClassName())->getTableName();
    }

    /**
     * @param array $data
     * @param $object
     * @param Event $event
     * @throws \Exception
     */
    protected function importObjectWithDimensionMappings(array $data, $object, Event $event)
    {
        $dimensionMappings = $event->getModule()->getServer()->getDimensionMappings();
        $mappingProblemsOccurred = false;

        $sysLanguageUids = [];
        $defaultDimensionMapping = null;
        $translationDimensionMappings = [];
        foreach ($dimensionMappings as $dimensionMapping) {
            if ($dimensionMapping->getLanguage() === 0) {
                $defaultDimensionMapping = $dimensionMapping;
            } else {
                $sysLanguageUids[] = (int)$dimensionMapping->getLanguage();
                $translationDimensionMappings[] = $dimensionMapping;
            }
        }

        if (!$object) {
            $object = $this->createObject($event);
        }

        // Notice: if for some reason - say, if dimensions were not configured - the system contains no dimension which
        // maps to the default language, then $defaultDimensionMapping will be null. Depending on whether or not the
        // remote system has enabled dimensions, the mapping may cause errors (if PIM has dimensions but local system
        // has not configured them, properties cannot map correctly).
        $rootObjectMappingProblemsOccurred = $this->mapPropertiesFromDataToObject($data, $object, $event->getModule(), $defaultDimensionMapping);
        $this->getObjectRepository()->update($object);
        $mappingProblemsOccurred = $mappingProblemsOccurred ?: $rootObjectMappingProblemsOccurred;

        if ($defaultDimensionMapping === null) {
            // This return is in place for TYPO3 configurations that don't contain dimension mapping. If the PIM wants
            // to deliver dimensions but none are configured, errors will most likely have been raised during mapping
            // right before this case - but even in case the mapping actually succeeds with pure null values, we put
            // a return here because there is no need to continue mapping dimensions to translations.
            return $mappingProblemsOccurred;
        }

        $dataMapper = GeneralUtility::makeInstance(DataMapper::class);
        foreach ($translationDimensionMappings as $translationDimensionMapping) {
            $existingRow = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', $this->getTableName(), 'sys_language_uid = ' . $translationDimensionMapping->getLanguage() . ' AND l10n_parent = ' . $object->getUid());
            if (is_array($existingRow)) {
                $translationObjects = $dataMapper->map($this->getEntityClassName(), [$existingRow]);
                $translationObject = current($translationObjects);
            } else {
                $translationObject = $this->createObject($event, $existingRow);
            }
            $translationObject->_setProperty('_languageUid', $translationDimensionMapping->getLanguage());
            $translationObject->setL10nParent($object);
            $objectMappingProblemsOccurred = $this->mapPropertiesFromDataToObject($data, $translationObject, $event->getModule(), $translationDimensionMapping);
            $mappingProblemsOccurred = $mappingProblemsOccurred ?: $objectMappingProblemsOccurred;
            $this->getObjectRepository()->update($translationObject);
        }

        if (!empty($sysLanguageUids)) {
            // Necessary to check if UID list is empty, or SQL query will be invalid (containing IN () condition).
            $GLOBALS['TYPO3_DB']->exec_DELETEquery($this->getTableName(), 'sys_language_uid NOT IN (' . implode(', ', $sysLanguageUids) . ') AND l10n_parent = ' . $object->getUid());
        }
        return $mappingProblemsOccurred;
    }

    protected function logProblem($message)
    {
        GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__)->alert($message);
    }
}
