<?php
namespace Crossmedia\Fourallportal\TypeConverter;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\AbstractTypeConverter;
use TYPO3\CMS\Extbase\Property\TypeConverter\PersistentObjectConverter;
use TYPO3\CMS\Extbase\Property\TypeConverterInterface;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

abstract class AbstractUuidAwareObjectTypeConverter extends PersistentObjectConverter implements TypeConverterInterface, PimBasedTypeConverterInterface
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
        if (is_numeric($source)) {
            $existingRecordUid = (int)$source;
        } else {
            $table = $this->getTableName($targetType);
            $existingRow = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
                'uid',
                $table,
                'remote_id = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($source, $table)
            );
            $existingRecordUid = $existingRow['uid'] ?? false;
        }
        if ($existingRecordUid) {
            return $this->getObjectByUidUnrestricted((int)$existingRecordUid);
        }
        return null;
    }

    protected function getObjectByUidUnrestricted(int $uid)
    {
        $query = $this->getRepository()->createQuery();
        //$query->getQuerySettings()->setLanguageMode('strict');
        //$query->getQuerySettings()->setLanguageUid($languageUid);
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->getQuerySettings()->setIncludeDeleted(true);
        $query->getQuerySettings()->setIgnoreEnableFields(true);
        //$query->getQuerySettings()->setRespectSysLanguage(false);
        $query->matching($query->equals('uid', $uid));
        $object = $query->execute()->getFirst();
        $object->_memorizeCleanState();
        return $object;
    }

    protected function getTableName(string $targetType) {

        return GeneralUtility::makeInstance(DataMapper::class)->getDataMap($targetType)->getTableName();
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
