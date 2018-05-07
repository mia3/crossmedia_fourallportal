<?php
declare(strict_types=1);
namespace Crossmedia\Fourallportal\ValueReader;

class ResponseDataFieldValueReader
{
    public function readResponseDataField(array $result, string $fieldName, string $dimensionName = null)
    {
        if (is_array($result['properties'][$fieldName][0] ?? false)
            && isset($result['properties'][$fieldName][0]['value'])
        ) {
            // This is a dimension capable response; return the value as such
            // TODO: handle dimensions once languages are available in both TYPO3 and PIM
            return $result['properties'][$fieldName][0]['value'];
        }
        return $result['properties'][$fieldName] ?? null;
    }
}
