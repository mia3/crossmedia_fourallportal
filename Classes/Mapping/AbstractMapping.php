<?php
namespace Crossmedia\Fourallportal\Mapping;

use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Service\ApiClient;
use Crossmedia\Products\Domain\Repository\ProductRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration;
use TYPO3\CMS\Extbase\Reflection\MethodReflection;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Extbase\Reflection\PropertyReflection;

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
        $map = MappingRegister::resolvePropertyMapForMapper(static::class);
        $properties = $data['result'][0]['properties'];
        foreach ($properties as $importedName => $propertyValue) {
            $targetPropertyName = isset($map[$importedName]) ? $map[$importedName] : $importedName;
            $this->mapPropertyValueToObject($targetPropertyName, $propertyValue, $object);
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
        $propertyMapper = $this->getAccessiblePropertyMapper();
        try {
            $targetType = $this->determineDataTypeForProperty($propertyName, $object);
            if (strpos($targetType, '<')) {
                $childType = substr($targetType, strpos($targetType, '<') + 1, -1);
                // Forcibly convert any array to an ObjectStorage
                $objectStorage = new ObjectStorage();
                foreach ((array) $propertyValue as $identifier) {
                    if (!$identifier) {
                        continue;
                    }
                    $child = $propertyMapper->findTypeConverter($identifier, $childType, $configuration)->convertFrom($identifier, $childType);
                    if (!$child) {
                        throw new \RuntimeException('Child of type ' . $childType . ' identified by ' . $identifier . ' could not be found');
                    }
                    $objectStorage->attach($child);
                }
                $propertyValue = $objectStorage;
            } else {
                if ($targetType !== $propertyMapper->determineSourceType($propertyValue)) {
                    $propertyValue = $propertyMapper->findTypeConverter($propertyValue, $targetType, $configuration)->convertFrom($propertyValue, $targetType);

                    // Sanity filter: do not attempt to set Entity with setter if an instance is required but the value is null.
                    if ((new \ReflectionMethod(get_class($object), 'set' . ucfirst($propertyName)))->getNumberOfRequiredParameters() === 1) {
                        if (is_null($propertyValue) && is_a($targetType, AbstractEntity::class, true)) {
                            return;
                        }
                    }
                }
            }

            ObjectAccess::setProperty($object, $propertyName, $propertyValue);
        } catch (\Exception $error) {
            $class = get_class($object);
            echo "Cannot map property $class::$propertyName, type $targetType with a value of " . var_export($propertyValue, true) . PHP_EOL;
            echo $error->getMessage() . PHP_EOL;
        }
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
    protected function getEntityClassName()
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
     * @return ProductRepository
     */
    protected function getObjectRepository()
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
        return $status;
    }
}
