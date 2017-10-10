<?php
namespace Crossmedia\Fourallportal\TypeConverter;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\ClassNamingUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Form\Domain\Runtime\Exception\PropertyMappingException;

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
        $dataMap = $this->objectManager->get(DataMapFactory::class)->buildDataMap(get_class($this->parentObject));
        $resourceFactory = ResourceFactory::getInstance();
        $model = new FileReference();

        // Lookup no. 1: try to find a sys_file_reference pointing to the sys_file with remote ID=$source
        // and matching relation values to $this->parentObject and $this->propertyName. We do this because
        // there is no Repository which we could use to load an Extbase file reference base on criteria.
        // So instead we probe the DB and if a match is found, we know the existing property value is the
        // exact same relation we were asked to convert - and we return the current property value.
        $queryBuilder = (new ConnectionPool())->getConnectionForTable('sys_file')->createQueryBuilder();
        $references = $queryBuilder->select('r.uid')->from('sys_file', 'f')->from('sys_file_reference', 'r')->where(
            sprintf(
                'r.uid_local = f.uid AND f.remote_id = \'%s\' AND r.tablenames = \'%s\' AND r.table_local = \'sys_file\' AND r.fieldname = \'%s\'',
                $source,
                $dataMap->getTableName(),
                GeneralUtility::camelCaseToLowerCaseUnderscored($this->propertyName)
            )
        )->setMaxResults(1)->execute()->fetchAll();

        if (isset($references[0]['uid'])) {
            // One reference with exact match to the current file was found. Return the existing property value.
            return ObjectAccess::getProperty($this->parentObject, $this->propertyName);
        }

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
            throw new \InvalidArgumentException('Unable to map ' . $this->propertyName . ' on ' . get_class($this->parentObject) . ': Asset ' . $source . ' does not appear to exist');
        }

        // File reference object needs to be created with the exact composition of this array. Not
        // passing either one of these parameters causes an invalid file reference to be written.
        $reference = $resourceFactory->createFileReferenceObject([
            'tablenames' => $dataMap->getTableName(),
            'table_local' => 'sys_file',
            'fieldname' => GeneralUtility::camelCaseToLowerCaseUnderscored($this->propertyName),
            'uid_local' => $original[0]['uid'],
            'uid_foreign' => $this->parentObject->getUid()
        ]);

        if (!isset($reference)) {
            throw new \InvalidArgumentException('A reference could not be resolved');
        }

        // New Extbase model FileReference instance is fitted with target and returned. The resulting
        // object persists correctly because the $reference above has had all properties manually set.
        $model->setOriginalResource($reference);
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
