<?php
namespace Crossmedia\Fourallportal\Mapping;

use Crossmedia\Fourallportal\Domain\Model\Module;

interface ValueSetterInterface
{
    public function setValueOnObject(
        $value,
        $sourcePropertyName,
        array $inputData,
        $object,
        Module $module,
        MappingInterface $mappingClass
    );
}
