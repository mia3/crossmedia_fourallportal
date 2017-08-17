<?php
namespace Crossmedia\Fourallportal\Mapping;

use TYPO3\CMS\Extbase\Property\PropertyMapper;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Property\TypeConverterInterface;

class AccessiblePropertyMapper extends PropertyMapper
{
    /**
     * @param mixed $source
     * @param string $targetType
     * @param PropertyMappingConfigurationInterface $configuration
     * @return TypeConverterInterface
     */
    public function findTypeConverter($source, $targetType, PropertyMappingConfigurationInterface $configuration)
    {
        return parent::findTypeConverter($source, $targetType, $configuration);
    }

    /**
     * @param mixed $source
     * @return string
     */
    public function determineSourceType($source)
    {
        return parent::determineSourceType($source);
    }
}
