<?php
namespace Crossmedia\Fourallportal\TypeConverter;

use Crossmedia\Fourallportal\Domain\Model\ComplexType;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Repository\ComplexTypeRepository;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\DynamicModel\ComplexTypeFactory;
use Crossmedia\Fourallportal\Mapping\MappingInterface;
use Crossmedia\Fourallportal\Mapping\MappingRegister;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Form\Domain\Runtime\Exception\PropertyMappingException;

class ComplexTypeConverter extends AbstractUuidAwareObjectTypeConverter implements PimBasedTypeConverterInterface
{
    /**
     * @var string
     */
    protected $targetType = ComplexType::class;

    /**
     * @var AbstractEntity
     */
    protected $parentObject;

    /**
     * @var string
     */
    protected $propertyName;

    /**
     * @var array
     */
    protected $sourceTypes = [
        'array'
    ];

    /**
     * @param AbstractEntity $object
     * @param string $propertyName
     * @return void
     */
    public function setParentObjectAndProperty($object, $propertyName)
    {
        $this->parentObject = $object;
        $this->propertyName = $propertyName;
    }

    /**
     * Converts an input remote ID to a FileReference pointing to the
     * File object which has the remote ID.
     *
     * If a File object with the remote ID is not found, the file gets
     * downloaded and a FileReference to the new File is returned.
     *
     * @param mixed $source
     * @param string $targetType
     * @param array $convertedChildProperties
     * @param PropertyMappingConfigurationInterface|null $configuration
     * @return ComplexType
     * @throws PropertyMappingException
     */
    public function convertFrom($source, $targetType, array $convertedChildProperties = [], PropertyMappingConfigurationInterface $configuration = null)
    {
        $mappingClass = get_class($this->getMapper());
        $map = MappingRegister::resolvePropertyMapForMapper($mappingClass);
        $originalFieldName = $map[array_search($this->propertyName, $map, true)] ?? GeneralUtility::camelCaseToLowerCaseUnderscored($this->propertyName);

        /** @var Module $module */
        $module = $this->getModuleRepository()->findOneByMappingClass($mappingClass);
        if (!$module) {
            throw new \RuntimeException(sprintf('No module exists which uses the mapping class "%s", cannot convert data', $mappingClass));
        }

        $moduleConfiguration = $module->getModuleConfiguration();
        $fieldConfiguration = $moduleConfiguration['field_conf'][$originalFieldName];

        /** @var ComplexType $existingComplexType */
        $existingComplexType = ObjectAccess::getProperty($this->parentObject, $this->propertyName);
        if ($existingComplexType) {
            $existingComplexType->setActualValue($source['value']);
            $existingComplexType->setNormalizedValue($source['normalized']);
            $existingComplexType->setLabel($source['unit']);
            $existingComplexType->setName($fieldConfiguration['metric']['name']);
            return $existingComplexType;
        }

        $templateComplexType = ComplexTypeFactory::getPreparedComplexType(
            $fieldConfiguration['type'],
            $fieldConfiguration
        );

        $templateComplexType->setNormalizedValue($source['normalized']);
        $templateComplexType->setActualValue($source['value']);
        $templateComplexType->setFieldName($originalFieldName);
        $templateComplexType->setParentUid($this->parentObject->getUid());
        $templateComplexType->setLabel($source['unit']);
        $templateComplexType->_setProperty('_languageUid', $this->parentObject->_getProperty('_languageUid'));
        $this->getRepository()->add($templateComplexType);

        return $templateComplexType;
    }

    /**
     * @return MappingInterface
     */
    protected function getMapper()
    {
        static $mappers = [];
        if (empty($mappers)) {
            foreach (MappingRegister::getMappings() as $className) {
                $mapper = GeneralUtility::makeInstance(ObjectManager::class)->get($className);
                $mappers[$mapper->getEntityClassName()] = $mapper;
            }
        }
        $targetEntityClassName = get_class($this->parentObject);
        if (isset($mappers[$targetEntityClassName])) {
            return $mappers[$targetEntityClassName];
        }
        throw new \RuntimeException(sprintf('No valid MappingInterface found for class "%s"', $targetEntityClassName));
    }

    /**
     * @return ModuleRepository
     */
    protected function getModuleRepository()
    {
        static $repository = null;
        if ($repository === null) {
            $repository = GeneralUtility::makeInstance(ObjectManager::class)->get(ModuleRepository::class);
        }
        return $repository;
    }

    /**
     * @return RepositoryInterface
     */
    protected function getRepository()
    {
        static $repository = null;
        if ($repository === null) {
            $repository = GeneralUtility::makeInstance(ObjectManager::class)->get(ComplexTypeRepository::class);
        }
        return $repository;
    }

}
