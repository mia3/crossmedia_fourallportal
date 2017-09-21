<?php
namespace Crossmedia\Fourallportal\TypeConverter;

use Crossmedia\Fourallportal\Domain\Model\ComplexType;
use Crossmedia\Fourallportal\Domain\Repository\ComplexTypeRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
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
     * @return FileReference
     * @throws PropertyMappingException
     */
    public function convertFrom($source, $targetType, array $convertedChildProperties = [], PropertyMappingConfigurationInterface $configuration = null)
    {
        $model = new ComplexType();
        return $model;
    }

    /**
     * @return RepositoryInterface
     */
    protected function getRepository()
    {
        return GeneralUtility::makeInstance(ObjectManager::class)->get(ComplexTypeRepository::class);
    }

}
