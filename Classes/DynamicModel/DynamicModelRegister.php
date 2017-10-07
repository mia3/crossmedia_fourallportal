<?php
namespace Crossmedia\Fourallportal\DynamicModel;

class DynamicModelRegister
{
    /**
     * @var array
     */
    protected static $handledModelClasses = [];

    /**
     * @param string $modelClassName
     */
    public static function registerModelForAutomaticHandling($modelClassName)
    {
        if (!in_array($modelClassName, static::$handledModelClasses)) {
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
}
