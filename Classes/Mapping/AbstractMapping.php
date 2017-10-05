<?php
namespace Crossmedia\Fourallportal\Mapping;

use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Service\ApiClient;
use Crossmedia\Fourallportal\TypeConverter\PimBasedTypeConverterInterface;
use Crossmedia\Products\Domain\Repository\ProductRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration;
use TYPO3\CMS\Extbase\Property\TypeConverter\PersistentObjectConverter;
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
     */
    public function import(array $data, Event $event)
    {
        $repository = $this->getObjectRepository();
        $objectId = $event->getObjectId();
        $object = $repository->findOneByRemoteId($objectId);

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
                if (!$object) {
                    $class = $this->getEntityClassName();
                    $object = new $class();
                    ObjectAccess::setProperty($object, 'remoteId', $objectId);
                }
                $this->mapPropertiesFromDataToObject($data, $object);
                $object->setPid($event->getModule()->getStoragePid());
                if ($object->getUid()) {
                    $repository->update($object);
                } else {
                    $repository->add($object);
                }
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
     * @param AbstractEntity $object
     */
    protected function mapPropertiesFromDataToObject(array $data, $object)
    {
        if (!$data['result']) {
            return;
        }
        $map = MappingRegister::resolvePropertyMapForMapper(static::class);
        $properties = $data['result'][0]['properties'];
        foreach ($properties as $importedName => $propertyValue) {
            if (($map[$importedName] ?? null) === false) {
                continue;
            }
            $customSetter = MappingRegister::resolvePropertyValueSetter(static::class, $importedName);
            if ($customSetter) {
                $customSetter->setValueOnObject($propertyValue, $importedName, $data, $object, $this);
            } else {
                $targetPropertyName = isset($map[$importedName]) ? $map[$importedName] : GeneralUtility::underscoredToLowerCamelCase($importedName);
                $this->mapPropertyValueToObject($targetPropertyName, $propertyValue, $object);
            }
        }
    }

    /**
     * @param string $propertyName
     * @param mixed $propertyValue
     * @param AbstractEntity $object
     */
    protected function mapPropertyValueToObject($propertyName, $propertyValue, $object)
    {
        if (!property_exists(get_class($object), $propertyName)) {
            return;
        }
        if ($propertyValue === null && !reset((new \ReflectionMethod(get_class($object), 'set' . ucfirst($propertyName)))->getParameters())->allowsNull()) {
            ObjectAccess::setProperty($object, $propertyName, null);
            return;
        }
        $configuration = new PropertyMappingConfiguration();

        $configuration->allowAllProperties();
        $configuration->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED, true);
        $configuration->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_MODIFICATION_ALLOWED, true);


        $propertyMapper = $this->getAccessiblePropertyMapper();
        $targetType = $this->determineDataTypeForProperty($propertyName, $object);
        if (strpos($targetType, '<')) {
            $childType = substr($targetType, strpos($targetType, '<') + 1, -1);
            $childType = trim($childType, '\\');
            $objectStorage = ObjectAccess::getProperty($object, $propertyName) ?? new ObjectStorage();
            foreach ((array) $propertyValue as $identifier) {
                if (!$identifier) {
                    continue;
                }
                $child = $propertyMapper->findTypeConverter($identifier, $childType, $configuration)->convertFrom($identifier, $childType, [], $configuration);
                if (!$child) {
                    throw new \RuntimeException('Child of type ' . $childType . ' identified by ' . $identifier . ' could not be found');
                }
                $objectStorage->attach($child);
            }
            $propertyValue = $objectStorage;
        } else {
            if ($targetType !== $propertyMapper->determineSourceType($propertyValue)) {
                $targetType = trim($targetType, '\\');
                $typeConverter = $propertyMapper->findTypeConverter($propertyValue, $targetType, $configuration);
                if ($typeConverter instanceof PimBasedTypeConverterInterface) {
                    $typeConverter->setParentObjectAndProperty($object, $propertyName);
                }
                $propertyValue = $typeConverter->convertFrom($propertyValue, $targetType, [], $configuration);

                if ($propertyValue instanceof Error) {
                    // For whatever reason, property validators will return a validation error rather than throw an exception.
                    // We therefore need to check this, log the problem, and skip the property.
                    GeneralUtility::sysLog(
                        'Error mapping ' . get_class($object) . '->' . $propertyName . ': ' . $propertyValue->getMessage(),
                        'fourallportal',
                        GeneralUtility::SYSLOG_SEVERITY_WARNING
                    );
                    return;
                }

                // Sanity filter: do not attempt to set Entity with setter if an instance is required but the value is null.
                if ((new \ReflectionMethod(get_class($object), 'set' . ucfirst($propertyName)))->getNumberOfRequiredParameters() === 1) {
                    if (is_null($propertyValue) && is_a($targetType, AbstractEntity::class, true)) {
                        return;
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
    }

    /**
     * @param $propertyName
     * @param $object
     * @return string|false
     */
    protected function determineDataTypeForProperty($propertyName, $object)
    {
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

        if (property_exists(get_class($object), $propertyName)) {
            $property = new PropertyReflection($object, $propertyName);
            $varTags = $property->getTagValues('var');
            if (!empty($varTags)) {
                return $varTags[0];
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
}
