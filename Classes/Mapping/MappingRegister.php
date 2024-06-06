<?php

namespace Crossmedia\Fourallportal\Mapping;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class MappingRegister
{
    /**
     * @var array
     */
    static protected array $mappings = [];

    /**
     * @var array
     */
    static protected array $propertyMaps = [];

    /**
     * @param $className
     * @param array $propertyMap
     * @return void
     */
    public static function registerMapping($className, array $propertyMap = array()): void
    {
        self::$mappings[$className] = $className;
        self::$propertyMaps[$className] = $propertyMap;
    }

    /**
     * @return array
     */
    public static function getMappings(): array
    {
        return self::$mappings;
    }

  /**
   * @param $className
   * @return array
   */
    public static function resolvePropertyMapForMapper($className): array
    {
        return self::$propertyMaps[$className];
    }

    /**
     * @param string $className
     * @param string $sourcePropertyName
     * @return ValueSetterInterface|null
     */
    public static function resolvePropertyValueSetter(string $className, string $sourcePropertyName): ?ValueSetterInterface
    {
        $targetPropertyMapping = static::resolvePropertyMapForMapper($className)[$sourcePropertyName] ?? null;
        if ($targetPropertyMapping && is_a($targetPropertyMapping, ValueSetterInterface::class, true)) {
            return GeneralUtility::makeInstance($targetPropertyMapping);
        }
        return null;
    }

    /**
     * @param array $config
     * @return array
     */
    public function getTcaSelectItems(array $config): array
    {
        $items = [];
        foreach (MappingRegister::getMappings() as $className => $name) {
            $items[] = array(0 => $name, 1 => $className);
        }
        $config['items'] = array_merge($config['items'], $items);
        return $config;
    }

}
