<?php
namespace Crossmedia\Fourallportal\Mapping;

class MappingRegister
{
    /**
     * @var array
     */
    static protected $mappings = [];

    /**
     * @test
     */
    public static function registerMapping($className)
    {
        self::$mappings[$className] = $className;
    }

    /**
     * @test
     */
    public static function getMappings()
    {
        return self::$mappings;
    }

    public function getTcaSelectItems($config) {
        var_dump('woot');
        $items = [];
        foreach (MappingRegister::getMappings() as $className => $name) {
            $items[] = array(0 => $name, 1 => $className);
        }
        $config['items'] = array_merge($config['items'], $items);
        return $config;
    }

}
