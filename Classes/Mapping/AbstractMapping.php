<?php
namespace Crossmedia\Fourallportal\Mapping;

use Crossmedia\Fourallportal\Domain\Model\DimensionMapping;
use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Service\ApiClient;
use Crossmedia\Fourallportal\TypeConverter\PimBasedTypeConverterInterface;
use Crossmedia\Fourallportal\ValueReader\ResponseDataFieldValueReader;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Log\Exception;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Session;
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
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;

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
        $logger = $this->getEventAndObjectSpecificLogger($event);
        if ($this->sanityCheckAndAutoRepair($data, $event, $logger)) {
            // A failed sanity check may mean DB contents have been repaired.
            // To be safe, we defer the event once so the next time it gets
            // processed the session will be clean.
            return true;
        }

        $objectId = $event->getObjectId();
        $repository = $this->getObjectRepository();
        $query = $repository->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->getQuerySettings()->setRespectSysLanguage(false);
        $query->getQuerySettings()->setIgnoreEnableFields(true);
        $query->getQuerySettings()->setLanguageUid(0);
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
                $logger->log(
                    LogLevel::INFO,
                    sprintf(
                        'Object %s was deleted by event %s:%d',
                        $event->getObjectId(),
                        $event->getModule()->getModuleName(),
                        $event->getEventId()
                    )
                );
                unset($object);
                break;
            case 'update':
            case 'create':
                $deferAfterProcessing = $this->importObjectWithDimensionMappings($data, $object, $event);
                $logger->log(
                    LogLevel::INFO,
                    sprintf(
                        'Object %s was updated by event %s:%d',
                        $event->getObjectId(),
                        $event->getModule()->getModuleName(),
                        $event->getEventId()
                    )
                );
                break;
            default:
                throw new \RuntimeException('Unknown event type: ' . $event->getEventType());
        }

        if (isset($object)) {
            $this->processRelationships($object, $data, $event);
        }

        if ($deferAfterProcessing) {
            $logger->notice(sprintf('Event %d was deferred', $event->getEventId()));
        }

        return $deferAfterProcessing;
    }

    /**
     * @param Event $event
     * @return LoggerInterface
     */
    protected function getEventAndObjectSpecificLogger(Event $event): LoggerInterface
    {
        $loggerName = '4ap_object_' . $event->getObjectId();
        $logger = new Logger($loggerName);
        $logger->addWriter(LogLevel::INFO, new FileWriter(['logFile' => $event->getObjectLogFilePath()]));
        $logger->addWriter(LogLevel::INFO, new FileWriter(['logFile' => $event->getEventLogFilePath()]));
        return $logger;
    }

    /**
     * Sanity check (local) data before allowing $event to be
     * processed. Returning TRUE defers the event.
     *
     * Happens before the Extbase persistence is engaged.
     *
     * Your sanity check should NOT use Extbase persistence
     * as this will cause the session to hold on to the objects
     * you load.
     *
     * If your sanity check was unable to repair whichever
     * problems it detected, make sure you log this via the
     * provided object logger!
     *
     * @param array $data
     * @param Event $event
     * @return bool
     */
    protected function sanityCheckAndAutoRepair(array $data, Event $event, LoggerInterface $objectLog): bool
    {
        $objectId = $event->getObjectId();
        $tableName = $this->getTableName();
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll();
        $pimRecords = $queryBuilder->select('*')
            ->from($tableName)
            ->where($queryBuilder->expr()->eq('remote_id', $queryBuilder->createNamedParameter($objectId)))
            ->orderBy('sys_language_uid', 'ASC')
            ->execute()
            ->fetchAll();
        $defer = false;

        if (empty($pimRecords)) {
            return $defer;
        }

        // Native check 1: Verify that all records from table associated with object have the same "pid",
        // have the right "l10n_parent" for translated records, and does not contain more than one record
        // for each unique language. Attempt to repair by deleting records (which will then be recreated).
        // Log anything that might be wrong/fixed.
        $languageUids = array_column($pimRecords, 'sys_language_uid');
        $pids = array_column($pimRecords, 'pid');
        $pid = reset($pids);
        if (count(array_unique($pids)) !== 1) {
            // One or more records do not have the right pid. Delete those that differ if their language UID is non-zero.
            $encountered = [];
            foreach ($pimRecords as $index => &$record) {
                if (!in_array($record['pid'], $encountered) && $record['sys_language_uid'] > 0) {
                    $objectLog->info(sprintf('Record %s from table %s has the wrong pid %d, setting it to %d', $record['uid'], $tableName, $record['pid'], $pid));
                    $record['pid'] = $pid;
                    $this->updateRecord($tableName, $record, $objectLog);
                    $defer = true;
                    unset($pimRecords[$index]);
                } else {
                    $encountered[] = $record['pid'];
                }
            }
        }
        if (count(array_unique($languageUids)) !== count($languageUids)) {
            // One or more records share the same language UID. We may have to remove some records.
            $encountered = [];
            foreach ($pimRecords as $index => $record) {
                if ($record['deleted']) {
                    $objectLog->info(sprintf('Record %s from table %s is soft-deleted; hard-delete it', $record['uid'], $tableName));
                    $this->hardDeleteRecord($tableName, $record, $objectLog);
                    continue;
                }
                if (in_array($record['sys_language_uid'], $encountered)) {
                    $objectLog->info(sprintf('Record %s from table %s is a language duplicate', $record['uid'], $tableName));
                    $this->hardDeleteRecord($tableName, $record, $objectLog);
                    $defer = true;
                    unset($pimRecords[$index]);
                } else {
                    $encountered[] = $record['sys_language_uid'];
                }
            }
        }

        return $defer;
    }

    protected function updateRecord(string $table, array $record, LoggerInterface $objectLog)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $query = $queryBuilder->update($table)->where($queryBuilder->expr()->eq('uid', $record['uid']));
        foreach ($record as $key => $value) {
            $query->set($key, $value);
        }
        $query->execute();
        $objectLog->info(sprintf('Record %s from table %s was updated', $record['uid'], $table));
    }

    protected function hardDeleteRecord(string $table, array $record, ?LoggerInterface $objectLog = null)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->delete($table)->where($queryBuilder->expr()->eq('uid', $record['uid']));
        $queryBuilder->execute();
        if ($objectLog) {
            $objectLog->info(sprintf('Record %s was deleted from table %s', $record['uid'], $table));
        }
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
                    $customSetter->setValueOnObject($propertyValue, $importedName, $data, $object, $module, $this, $dimensionMapping);
                } else {
                    $targetPropertyName = isset($map[$importedName]) ? $map[$importedName] : GeneralUtility::underscoredToLowerCamelCase($importedName);
                    $propertyMappingProblemsOccurred = $this->mapPropertyValueToObject($targetPropertyName, $propertyValue, $object);
                    $mappingProblemsOccurred = $mappingProblemsOccurred ?: $propertyMappingProblemsOccurred;
                }
            } catch (PropertyNotAccessibleException $error) {
                $this->logProblem('Error mapping ' . $module->getModuleName() . ':' . $object->getRemoteId() . ':' . $importedName .' - ' . $error->getMessage());
            } catch (DeferralException $error) {
                $this->logProblem('Error mapping ' . $module->getModuleName() . ':' . $object->getRemoteId() . ':' . $importedName .' - ' . $error->getMessage());
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

        /*
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
        */

        if ($propertyValue === null && reset((new \ReflectionMethod(get_class($object), 'set' . ucfirst($propertyName)))->getParameters())->allowsNull()) {
            ObjectAccess::setProperty($object, $propertyName, null);
            return false;
        }
        $configuration = new PropertyMappingConfiguration();
        $mappingProblemsOccurred = false;

        $configuration->allowAllProperties();
        $configuration->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED, true);
        $configuration->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_MODIFICATION_ALLOWED, true);
        $configuration->setTypeConverterOption(DateTimeConverter::class, DateTimeConverter::CONFIGURATION_DATE_FORMAT, 'Y#m#d\\TH#i#s+');

        $propertyMapper = $this->getAccessiblePropertyMapper();
        $targetType = $this->determineDataTypeForProperty($propertyName, $object);
        if (strpos($targetType, '<')) {
            $childType = substr($targetType, strpos($targetType, '<') + 1, -1);
            $childType = trim($childType, '\\');
            $objectStorage = new ObjectStorage();

            if (!empty($propertyValue)) {
                foreach ((array) $propertyValue as $identifier) {
                    if (!$identifier) {
                        continue;
                    }
                    $typeConverter = $propertyMapper->findTypeConverter($identifier, $childType, $configuration);
                    if ($typeConverter instanceof PimBasedTypeConverterInterface) {
                        $typeConverter->setParentObjectAndProperty($object, $propertyName);
                    }

                    try {
                        $child = $typeConverter->convertFrom($identifier, $childType, [], $configuration);
                    } catch (DeferralException $error) {
                        $this->logProblem($error->getMessage());
                        $mappingProblemsOccurred = true;
                        continue;
                    }

                    if ($child instanceof Error) {
                        // For whatever reason, property validators will return a validation error rather than throw an exception.
                        // We therefore need to check this, log the problem, and skip the property.
                        $this->logProblem(
                            'Mapping error when mapping property ' . $propertyName . ' on ' . get_class($object) . ':' .  $object->getRemoteId() .
                            ' in language UID ' . ObjectAccess::getProperty($object, '_languageUid', true) . ': ' . $child->getMessage()
                        );
                        $child = null;
                    }

                    if (!$child) {
                        $this->logProblem(
                            'Child of type ' . $childType . ' identified by ' . $identifier . ' not found when mapping property ' .
                            $propertyName . ' on ' . get_class($object) . ':' .  $object->getRemoteId() . ' in language UID ' .
                            ObjectAccess::getProperty($object, '_languageUid', true)
                        );
                        $mappingProblemsOccurred = true;
                        continue;
                    }

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
                    //$mappingProblemsOccurred = true;
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
        } elseif ($propertyValue === null && !reset((new \ReflectionMethod(get_class($object), 'set' . ucfirst($propertyName)))->getParameters())->allowsNull()) {
            $this->logProblem(
                sprintf(
                    'Property "%s" on object "%s->%s" does not allow NULL as value, but NULL was resolved. Please verify PIM response data consistency!',
                    $propertyName,
                    get_class($object),
                    method_exists($object, 'getRemoteId') ? $object->getRemoteId() : $object->getUid()
                )
            );
            return false;
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
        foreach ($moduleConfiguration['relation_conf'] as $field) {
            if (!isset($properties[$field['name']])) {
                $properties[$field['name']]['value'] = null;
            }
        }
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
     * @param int $systemLanguage
     * @param int $languageParentUid
     * @param null $existingRow
     * @return mixed
     */
    protected function createObject(Event $event, int $systemLanguage = 0, int $languageParentUid = 0, $existingRow = null)
    {
        if ($systemLanguage > 0 && $languageParentUid === 0) {
            throw new \Exception(
                sprintf(
                    'Will not create record for "%s:%s" in language "%d" since no translation parent was provided.',
                    $event->getModule()->getModuleName(),
                    $event->getObjectId(),
                    $systemLanguage
                )
            );
        }

        $recordUid = (int)(($existingRow['l10n_parent'] ?? false) ?: ($existingRow['uid'] ?? false) ?: 0);

        if ($recordUid === 0) {
            $newRecordValues = [
                'pid' => $event->getModule()->getStoragePid(),
                'sys_language_uid' => $systemLanguage,
                'l10n_parent' => $languageParentUid,
                'remote_id' => $event->getObjectId(),
                'crdate' => time(),
            ];
            $GLOBALS['TYPO3_DB']->exec_INSERTquery($this->getTableName(), $newRecordValues);
            $insertedRecordUid = $GLOBALS['TYPO3_DB']->sql_insert_id();
            $recordUid = $insertedRecordUid;
        }

        $this->persist();;

        $entityClassName = $event->getModule()->getMapper()->getEntityClassName();
        $session = GeneralUtility::makeInstance(ObjectManager::class)->get(Session::class);
        if ($session->hasIdentifier($recordUid, $entityClassName)) {
            $recordedObject = $session->getObjectByIdentifier($recordUid, $entityClassName);
            $session->unregisterObject($recordedObject);
            $session->unregisterReconstitutedEntity($recordedObject);
        }

        if ($languageParentUid > 0 && $session->hasIdentifier($languageParentUid, $entityClassName)) {
            $recordedObject = $session->getObjectByIdentifier($languageParentUid, $entityClassName);
            $session->unregisterObject($recordedObject);
            $session->unregisterReconstitutedEntity($recordedObject);
        }

        $query = $this->getObjectRepository()->createQuery();
        $query->getQuerySettings()
            ->setRespectSysLanguage(false)
            ->setIncludeDeleted(false)
            ->setIgnoreEnableFields(true)
            ->setRespectStoragePage(false)
            ->setLanguageUid($systemLanguage);
            //->setLanguageMode('strict')
            //->setLanguageOverlayMode('hideNonTranslated');

        $createdObject = $query->matching($query->equals('remote_id', $event->getObjectId()))->execute()->getFirst();
        if (!$createdObject) {
            throw new \Exception(
                sprintf(
                    'Unable to create object "%s:%s" in language "%d". Expected record UID: %s',
                    $event->getModule()->getModuleName(),
                    $event->getObjectId(),
                    $systemLanguage,
                    $recordUid
                )
            );
        }

        if ($systemLanguage) {
            $createdObject->_setProperty('_localizedUid', $existingRow['uid'] ?? $recordUid);
            $createdObject->_setProperty('_languageUid', $systemLanguage);
            $createdObject->setRemoteId($event->getObjectId());
        }
        return $createdObject;
    }

    /**
     * Persists all objects pending for ORM
     *
     * @return void
     */
    protected function persist()
    {
        $persistenceManager = GeneralUtility::makeInstance(ObjectManager::class)->get(PersistenceManager::class);
        $persistenceManager->persistAll();
        #$persistenceManager->clearState();
    }

    public function getTableName()
    {
        $dataMapper = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper::class);
        return $dataMapper->getDataMap($this->getEntityClassName())->getTableName();
    }

    /**
     * @param array $data
     * @param $object
     * @param Event $event
     * @throws \Exception
     * @return bool
     */
    protected function importObjectWithDimensionMappings(array $data, $object, Event $event)
    {
        $GLOBALS['TSFE'] = new TypoScriptFrontendController($GLOBALS['TYPO3_CONF_VARS'], $event->getModule()->getStoragePid(), 0);
        $GLOBALS['TSFE']->sys_page = new PageRepository();
        $GLOBALS['TSFE']->sys_page->sys_language_uid = 0;
        $GLOBALS['TSFE']->getPageAndRootline();
        $GLOBALS['TSFE']->sys_language_content = 0;
        $GLOBALS['TSFE']->config['sys_language_uid'] = 0;
        $GLOBALS['TSFE']->settingLanguage();

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
            //$object->setRemoteId($event->getObjectId());
            $this->persist();
        }

        // Notice: if for some reason - say, if dimensions were not configured - the system contains no dimension which
        // maps to the default language, then $defaultDimensionMapping will be null. Depending on whether or not the
        // remote system has enabled dimensions, the mapping may cause errors (if PIM has dimensions but local system
        // has not configured them, properties cannot map correctly).
        $rootObjectMappingProblemsOccurred = $this->mapPropertiesFromDataToObject($data, $object, $event->getModule(), $defaultDimensionMapping);
        $this->getObjectRepository()->update($object);
        $this->persist();

        $mappingProblemsOccurred = $mappingProblemsOccurred ?: $rootObjectMappingProblemsOccurred;

        if ($defaultDimensionMapping === null || !$event->getModule()->getContainsDimensions()) {
            // This return is in place for TYPO3 configurations that don't contain dimension mapping. If the PIM wants
            // to deliver dimensions but none are configured, errors will most likely have been raised during mapping
            // right before this case - but even in case the mapping actually succeeds with pure null values, we put
            // a return here because there is no need to continue mapping dimensions to translations.
            // Alternative case is that dimension mapping is disabled for the specific module, which is possible to do
            // if the PIM service absolutely has no dimensioned data (e.g. the "data" module).
            return $mappingProblemsOccurred;
        }

        $persistenceSession = GeneralUtility::makeInstance(ObjectManager::class)->get(Session::class);
        $persistenceSession->unregisterObject($object);

        foreach ($translationDimensionMappings as $translationDimensionMapping) {

            if (!$translationDimensionMapping->isActive()) {
                $this->logProblem('Dimension mapping ' . $translationDimensionMapping->getUid() . ' is configured to not use dimensions, skipping.');
                continue;
            }

            #$persistenceSession->destroy();
            $languageUid = $translationDimensionMapping->getLanguage();

            /*
            $GLOBALS['TSFE']->sys_language_content = $languageUid;
            $GLOBALS['TSFE']->sys_page->sys_language_uid = $languageUid;
            $GLOBALS['TSFE']->config['sys_language_uid'] = $languageUid;
            $GLOBALS['TSFE']->settingLanguage();
            */
            $GLOBALS['TSFE'] = new TypoScriptFrontendController($GLOBALS['TYPO3_CONF_VARS'], $event->getModule()->getStoragePid(), 0);
            $GLOBALS['TSFE']->sys_page = new PageRepository();
            $GLOBALS['TSFE']->sys_page->sys_language_uid = $languageUid;
            $GLOBALS['TSFE']->getPageAndRootline();
            $GLOBALS['TSFE']->sys_language_content = $languageUid;
            $GLOBALS['TSFE']->config['sys_language_uid'] = 0;
            $GLOBALS['TSFE']->settingLanguage();

            // the soft delete feature needs to be taken into account, otherwise a deleted record might be updated
            // and thus will not have any effect in the frontend and backend
            $softDeleteField = $this->getSoftDeleteFieldForTable($this->getTableName());
            if ($softDeleteField !== '') {
                $softDeleteField = ' AND ' . $softDeleteField . ' = 0';
            }

            $existingRow = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
                'uid,l10n_parent,sys_language_uid',
                $this->getTableName(),
                'sys_language_uid = ' . $languageUid . ' AND l10n_parent = ' . $object->getUid() . $softDeleteField
            );

            $translationObject = $this->createObject($event, $languageUid, $object->getUid(), $existingRow);
            //$translationObject->setRemoteId($event->getObjectId());
            $objectMappingProblemsOccurred = $this->mapPropertiesFromDataToObject($data, $translationObject, $event->getModule(), $translationDimensionMapping);
            $mappingProblemsOccurred = $mappingProblemsOccurred ?: $objectMappingProblemsOccurred;
            $this->getObjectRepository()->update($translationObject);
            $this->persist();
            $persistenceSession->unregisterObject($translationObject);
        }

        #$persistenceSession->destroy();
        #$persistenceSession->registerObject($event, get_class($event) . ':' . $event->getUid());
        GeneralUtility::makeInstance(ObjectManager::class)->get(Session::class)->registerObject($event, get_class($event) . ':' . $event->getUid());

        return $mappingProblemsOccurred;
    }

    protected function getSoftDeleteFieldForTable(string $tablename): string
    {
        if (! empty($GLOBALS['TCA'][$tablename]['ctrl']['delete'])) {
            return $GLOBALS['TCA'][$tablename]['ctrl']['delete'];
        }
        return '';
    }

    protected function logProblem($message)
    {
        GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__)->alert($message);
    }
}
