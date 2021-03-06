<?php
namespace Crossmedia\Fourallportal\Domain\Model;

/***
 *
 * This file is part of the "4AllPortal Connector" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Marc Neuhaus <marc@mia3.com>, MIA3 GmbH & Co. KG
 *
 ***/

/**
 * ComplexType
 */
class ComplexType extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $labelMax;

    /**
     * @var string
     */
    protected $normalizedValue;

    /**
     * @var string
     */
    protected $actualValue;

    /**
     * @var string
     */
    protected $normalizedValueMax;

    /**
     * @var string
     */
    protected $actualValueMax;

    /**
     * @var string
     */
    protected $castType;

    /**
     * @var int
     */
    protected $parentUid;

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * @param string $fieldName
     */
    public function setFieldName(string $fieldName)
    {
        $this->fieldName = $fieldName;
    }

    /**
     * @return mixed
     */
    public function getNormalizedValue()
    {
        return $this->castValue($this->normalizedValue, $this->castType);
    }

    /**
     * @param mixed $normalizedValue
     */
    public function setNormalizedValue($normalizedValue)
    {
        $this->castType = gettype($normalizedValue);
        $this->normalizedValue = $normalizedValue;
    }

    /**
     * @return mixed
     */
    public function getActualValue()
    {
        return $this->castValue($this->actualValue, $this->castType);
    }

    /**
     * @param mixed $actualValue
     */
    public function setActualValue($actualValue)
    {
        $this->castType = gettype($actualValue);
        $this->actualValue = $actualValue;
    }

    /**
     * @return string
     */
    public function getLabelMax(): string
    {
        return $this->labelMax;
    }

    /**
     * @param string $labelMax
     */
    public function setLabelMax(string $labelMax): void
    {
        $this->labelMax = $labelMax;
    }

    /**
     * @return string
     */
    public function getNormalizedValueMax()
    {
        return $this->normalizedValueMax;
    }

    /**
     * @param string $normalizedValueMax
     */
    public function setNormalizedValueMax($normalizedValueMax)
    {
        $this->normalizedValueMax = $normalizedValueMax;
    }

    /**
     * @return string
     */
    public function getActualValueMax()
    {
        return $this->actualValueMax;
    }

    /**
     * @param string $actualValueMax
     */
    public function setActualValueMax($actualValueMax)
    {
        $this->actualValueMax = $actualValueMax;
    }

    /**
     * @return string
     */
    public function getCastType()
    {
        return $this->castType;
    }

    /**
     * @param string $castType
     */
    public function setCastType($castType)
    {
        $this->castType = $castType;
    }

    /**
     * @return int
     */
    public function getParentUid()
    {
        return $this->parentUid;
    }

    /**
     * @param int $parentUid
     */
    public function setParentUid($parentUid)
    {
        $this->parentUid = $parentUid;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getActualValue();
    }

    /**
     * @param mixed $value
     * @param string $type
     * @return float|int
     */
    protected function castValue($value, $type)
    {
        settype($value, $type);
        return is_numeric($value) ? $value : 0;
    }


}
