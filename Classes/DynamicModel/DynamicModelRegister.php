<?php
namespace Crossmedia\Fourallportal\DynamicModel;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class DynamicModelRegister
{
    /**
     * @var array
     */
    protected static $handledModelClasses = [];

    /**
     * @var array
     */
    protected static $overriddenSqlTypes = [];

    /**
     * @param string $modelClassName
     */
    public static function registerModelForAutomaticHandling($modelClassName)
    {
        if (!in_array($modelClassName, static::$handledModelClasses)) {
            $segments = explode('\\', $modelClassName);
            $className = array_pop($segments);
            $segments[] = 'Abstract' . $className;
            $abstractClassName = implode('\\', $segments);
            if (!class_exists($abstractClassName)) {
                class_alias(AbstractEntity::class, $abstractClassName);
            }
            static::$handledModelClasses[] = $modelClassName;
        }
    }

    /**
     * @return array
     */
    public static function getModelClassNamesRegisteredForAutomaticHandling()
    {
        return static::$handledModelClasses;
    }

    /**
     * @param string $modelClassName
     * @return bool
     */
    public static function isModelRegisteredForAutomaticHandling($modelClassName)
    {
        return in_array($modelClassName, static::$handledModelClasses);
    }

    /**
     * @param string $table
     * @param string $column
     * @param string $type
     */
    public static function overrideSqlType($table, $column, $type)
    {
        static::$overriddenSqlTypes[$table][$column] = $type;
    }

    /**
     * @param string $table
     * @param string $column
     * @param string $originalType
     * @return mixed
     */
    public static function getOverriddenOrOriginalSqlType($table, $column, $originalType)
    {
        return static::$overriddenSqlTypes[$table][$column] ?? $originalType;
    }
}
