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

use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

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
    protected $normalizedValue;

    /**
     * @var string
     */
    protected $actualValue;

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
     * @param ComplexType $other
     * @return boolean
     */
    public function equals(ComplexType $other)
    {
        return (
            $this->getType() === $other->getType()
            && $this->getName() === $other->getName()
            && $this->getFieldName() === $other->getFieldName()
        );
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
     * @return mixed
     */
    protected function castValue($value, $type)
    {
        settype($value, $type);
        return $value;
    }


}
