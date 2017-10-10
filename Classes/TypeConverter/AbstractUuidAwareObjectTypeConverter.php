<?php
namespace Crossmedia\Fourallportal\TypeConverter;

use Crossmedia\Fourallportal\DynamicModel\DynamicModelGenerator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\AbstractTypeConverter;
use TYPO3\CMS\Extbase\Property\TypeConverterInterface;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

abstract class AbstractUuidAwareObjectTypeConverter extends AbstractTypeConverter implements TypeConverterInterface, PimBasedTypeConverterInterface
{
    /**
     * @var AbstractEntity
     */
    protected $parent;

    /**
     * @var string
     */
    protected $propertyName;

    /**
     * Converters convert from strings (UUIDs) and integers (UIDs).
     * Contrary to the generic persisted object converter, this type
     * of converter does not support arrays as input (neither full
     * property arrays nor arrays containing __identity). When an
     * array is received the generic converter will be used instead.
     *
     * @var array<string>
     * @api
     */
    protected $sourceTypes = [
        'string',
        'integer'
    ];

    /**
     * @param AbstractEntity $object
     * @param string $propertyName
     * @return void
     */
    public function setParentObjectAndProperty($object, $propertyName)
    {
        $this->parent = $object;
        $this->propertyName = $propertyName;
    }

    /**
     * @param mixed $source
     * @param string $targetType
     * @param array $convertedChildProperties
     * @param PropertyMappingConfigurationInterface|null $configuration
     * @return object
     */
    public function convertFrom($source, $targetType, array $convertedChildProperties = [], PropertyMappingConfigurationInterface $configuration = null)
    {
        $repository = $this->getRepository();
        $fromRemoteId = $repository->findOneByRemoteId($source);
        if ($fromRemoteId) {
            return $fromRemoteId;
        }

        $candidate = $repository->findByUid((integer) $source);
        if (!$candidate) {
            $candidate = $this->autoCreateObject($repository, $source);
        }
        return $candidate;
    }

    /**
     * @param RepositoryInterface $repository
     * @param string $remoteId
     * @return AbstractEntity
     */
    protected function autoCreateObject($repository, $remoteId)
    {
        // In all likelihood, desynced queue events which contain a reference that hasn't yet been created.
        // We create it now, and persist it, to make sure it is fetchable even though it hasn't technically been created (property-filled from API) yet.
        $class = $this->getSupportedTargetType();
        $candidate = new $class();
        ObjectAccess::setProperty($candidate, 'remoteId', $remoteId);
        $repository->add($candidate);
        GeneralUtility::makeInstance(ObjectManager::class)->get(PersistenceManager::class)->persistAll();
        return $candidate;
    }

    /**
     * @return RepositoryInterface
     */
    protected function getRepository()
    {
        return GeneralUtility::makeInstance(ObjectManager::class)->get(ltrim(str_replace('\\Domain\\Model\\', '\\Domain\\Repository\\', $this->getSupportedTargetType()) . 'Repository', '\\'));
    }

    /**
     * This type of TypeConverter has a naturally very high priority,
     * since it catches one of the same types (int) as the standard
     * generic persisted object converter.
     *
     * @return int
     */
    public function getPriority()
    {
        return 90;
    }
}
