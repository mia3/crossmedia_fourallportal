<?php
namespace Crossmedia\Fourallportal\Mapping;

use Exception;
use TYPO3\CMS\Extbase\Property\Exception\InvalidSourceException;
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
     * @throws Exception
     */
    public function findTypeConverter($source, string $targetType, PropertyMappingConfigurationInterface $configuration): TypeConverterInterface
    {
        return parent::findTypeConverter($source, $targetType, $configuration);
    }

    /**
     * @param mixed $source
     * @return string
     * @throws InvalidSourceException
     */
    public function determineSourceType($source): string
    {
        return parent::determineSourceType($source);
    }
}
