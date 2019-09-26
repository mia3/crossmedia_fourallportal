<?php
namespace Crossmedia\Fourallportal\TypeConverter;

use Crossmedia\Fourallportal\Mapping\DeferralException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

class FileReferenceTypeConverter extends AbstractUuidAwareObjectTypeConverter implements PimBasedTypeConverterInterface
{
    /**
     * @var string
     */
    protected $targetType = FileReference::class;

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
        'string'
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
     * @param mixed $source
     * @param string $targetType
     * @param array $convertedChildProperties
     * @param PropertyMappingConfigurationInterface|null $configuration
     * @return FileReference
     */
    public function convertFrom($source, $targetType, array $convertedChildProperties = [], PropertyMappingConfigurationInterface $configuration = null)
    {
        $dataMap = $this->objectManager->get(DataMapFactory::class)->buildDataMap(get_class($this->parentObject));
        $resourceFactory = ResourceFactory::getInstance();

        $systemLanguageUid = (int)$this->parentObject->_getProperty('_languageUid');
        $fieldName = $dataMap->getColumnMap($this->propertyName)->getColumnName(); // GeneralUtility::camelCaseToLowerCaseUnderscored($this->propertyName);

        // Lookup no. 1: try to find a sys_file_reference pointing to the sys_file with remote ID=$source
        // and matching relation values to $this->parentObject and $this->propertyName. We do this because
        // there is no Repository which we could use to load an Extbase file reference base on criteria.
        // So instead we probe the DB and if a match is found, we know the existing property value is the
        // exact same relation we were asked to convert - and we return the current property value.
        $queryBuilder = (new ConnectionPool())->getConnectionForTable('sys_file')->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $query = $queryBuilder->select('r.*')->from('sys_file', 'f')->from('sys_file_reference', 'r')->where(
            sprintf(
                'r.uid_local = f.uid AND f.remote_id = \'%s\' AND r.tablenames = \'%s\' AND r.fieldname = \'%s\' AND r.uid_foreign = %d AND r.sys_language_uid = %d',
                $source,
                $dataMap->getTableName(),
                $fieldName,
                $this->parentObject->getUid(),
                $systemLanguageUid
            )
        )->setMaxResults(1);
        $references = $query->execute()->fetchAll();
        if (isset($references[0]['uid'])) {
            return $this->fetchObjectFromPersistence((int) $references[0]['uid'], $targetType);
        } else {

            // Lookup no. 2: try to find a sys_file with remote ID=$source and use it as target for a new
            // file relation. If the original file cannot be found this way the relation is considered
            // invalid or impossible to resolve - and an exception is thrown, causing the importing to be
            // resumed on next run which should then have imported the target file so we can point to it.
            $queryBuilder = (new ConnectionPool())->getConnectionForTable('sys_file')->createQueryBuilder();
            $original = $queryBuilder->select('f.uid')->from('sys_file', 'f')
                ->where($queryBuilder->expr()->eq('f.remote_id', $queryBuilder->quote($source)))
                ->setMaxResults(1)
                ->execute()
                ->fetchAll();
            if (!isset($original[0]['uid'])) {
                $parentObjectId = method_exists($this->parentObject, 'getRemoteId') ? $this->parentObject->getRemoteId() : $this->parentObject->getUid();
                throw new DeferralException(
                    'Unable to map ' . $this->propertyName . ' on ' . get_class($this->parentObject) . ':' . $parentObjectId .
                    ' - Asset ' . $source . ' does not appear to exist (yet).',
                    1527167261
                );
            }

            // File reference object needs to be created with the exact composition of this array. Not
            // passing either one of these parameters causes an invalid file reference to be written.
            $referenceProperties = [
                //'pid' => $this->parentObject->getPid(),
                'tablenames' => $dataMap->getTableName(),
                'table_local' => 'sys_file',
                'fieldname' => $fieldName,
                'uid_local' => $original[0]['uid'],
                'uid_foreign' => $this->parentObject->getUid(),
                'sys_language_uid' => $systemLanguageUid,
            ];

        }

        if ($systemLanguageUid > 0) {
            // Select the original language version of this particular file reference so the UID can
            // be used as t3_origuid and l10n_parent value in the localized reference's record.
            // Note that it is possible that there is no default language record (emulated free mode
            // in IRRE) so we only create the reference as a localized version IF there is a default.
            $queryBuilder = (new ConnectionPool())->getConnectionForTable('sys_file')->createQueryBuilder();
            $query = $queryBuilder->select('r.uid')->from('sys_file', 'f')->from('sys_file_reference', 'r')->where(
                sprintf(
                    'r.uid_local = f.uid AND f.remote_id = \'%s\' AND r.tablenames = \'%s\' AND r.table_local = \'sys_file\' AND r.fieldname = \'%s\' AND r.uid_foreign = %d AND r.sys_language_uid = 0',
                    $source,
                    $dataMap->getTableName(),
                    $fieldName,
                    $this->parentObject->getUid()
                )
            )->setMaxResults(1);
            $originalLanguageReferenceRecord = $query->execute()->fetch();
            if ($originalLanguageReferenceRecord) {
                $referenceProperties['l10n_parent'] = $originalLanguageReferenceRecord['uid'];
                $referenceProperties['t3_origuid'] = $originalLanguageReferenceRecord['uid'];
                $referenceProperties['uid_foreign'] = $this->parentObject->_getProperty('_localizedUid');
            }
        }

        $reference = $resourceFactory->createFileReferenceObject($referenceProperties);

        if (!isset($reference)) {
            throw new DeferralException(
                'Unable to map ' . $this->propertyName . ' on ' . get_class($this->parentObject) . ':' . $this->parentObject->getUid() .
                ' - Asset ' . $source . ' does not appear to exist (yet).',
                1527167262
            );
        }

        // New Extbase model FileReference instance is fitted with target and returned. The resulting
        // object persists correctly because the $reference above has had all properties manually set.
        $model = new FileReference();
        $model->_memorizeCleanState();
        $model->setOriginalResource($reference);
        $model->_setProperty('_languageUid', $systemLanguageUid);
        if ($systemLanguageUid > 0 && ($originalLanguageReferenceRecord['uid'] ?? false)) {
            $model->_setProperty('_localizedUid', $reference->getUid());
        }
        //$model->setPid($this->parentObject->getPid());
        return $model;
    }

    /**
     * @return RepositoryInterface
     */
    protected function getRepository()
    {
        return GeneralUtility::makeInstance(ObjectManager::class)->get(FileRepository::class);
    }

}
