<?php
declare(strict_types=1);
namespace Crossmedia\Fourallportal\ValueReader;

use Crossmedia\Fourallportal\Domain\Model\DimensionMapping;
use TYPO3\CMS\Extbase\Reflection\Exception\PropertyNotAccessibleException;

class ResponseDataFieldValueReader
{
    public function readResponseDataField(array $result, string $fieldName, DimensionMapping $dimensionMapping = null)
    {
        if (is_array($result['properties'][$fieldName] ?? false)
            && array_key_exists('value', $result['properties'][$fieldName][0] ?? [])
            && is_array($result['properties'][$fieldName][0]['dimensions'] ?? false)
        ) {
            if ($dimensionMapping !== null) {
                foreach ($result['properties'][$fieldName] as $dimensionObject) {
                    if ($dimensionMapping->matches($dimensionObject['dimensions'])) {
                        return $dimensionObject['value'];
                    }
                }
            }
            if (empty($result['properties'][$fieldName][0]['dimensions'])) {
                // This is a dimension capable response, but the dimensions are empty; return the value since it applies to
                // every possible translation.
                return $result['properties'][$fieldName][0]['value'];
            }
            throw new PropertyNotAccessibleException(
                'Cannot read property ' . $fieldName . ' from PIM response. ' .
                (
                    $dimensionMapping === null
                        ? 'Dimension mapping is NOT in effect but property contains dimensions'
                        : 'Dimension mapping is in effect but no dimensions match the language being imported'
                ),
                1527168391
            );
        }
        return $result['properties'][$fieldName]['value'] ?? $result['properties'][$fieldName] ?? null;
    }
}
