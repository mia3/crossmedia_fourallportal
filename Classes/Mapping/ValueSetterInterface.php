<?php
namespace Crossmedia\Fourallportal\Mapping;

interface ValueSetterInterface
{
    public function setValueOnObject($value, $sourcePropertyName, array $inputData, $object, MappingInterface $mappingClass);
}
