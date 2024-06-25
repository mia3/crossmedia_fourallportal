<?php

namespace Crossmedia\Fourallportal\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

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
class ComplexType extends AbstractEntity
{
    protected string $type;
    protected string $name;
    protected string $fieldName;
    protected string $label;
    protected string $labelMax;
    protected string $normalizedValue;
    protected string $actualValue;
    protected string $normalizedValueMax;
    protected string $actualValueMax;
    protected string $castType;
    protected int $parentUid;

    public function getType(): string
    {
        return $this->type;
    }

    public function setType($type): void
    {
        $this->type = $type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName($name): void
    {
        $this->name = $name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel($label): void
    {
        $this->label = $label;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function setFieldName(string $fieldName): void
    {
        $this->fieldName = $fieldName;
    }

    public function getNormalizedValue(): mixed
    {
        return $this->castValue($this->normalizedValue, $this->castType);
    }

    public function setNormalizedValue(mixed $normalizedValue): void
    {
        $this->castType = gettype($normalizedValue);
        $this->normalizedValue = $normalizedValue;
    }

    public function getActualValue(): mixed
    {
        return $this->castValue($this->actualValue, $this->castType);
    }

    public function setActualValue(mixed $actualValue): void
    {
        $this->castType = gettype($actualValue);
        $this->actualValue = $actualValue;
    }

    public function getLabelMax(): string
    {
        return $this->labelMax;
    }

    public function setLabelMax(string $labelMax): void
    {
        $this->labelMax = $labelMax;
    }

    public function getNormalizedValueMax(): string
    {
        return $this->normalizedValueMax;
    }

    public function setNormalizedValueMax(string $normalizedValueMax): void
    {
        $this->normalizedValueMax = $normalizedValueMax;
    }

    public function getActualValueMax(): string
    {
        return $this->actualValueMax;
    }

    public function setActualValueMax(string $actualValueMax): void
    {
        $this->actualValueMax = $actualValueMax;
    }

    public function getCastType(): string
    {
        return $this->castType;
    }

    public function setCastType(string $castType): void
    {
        $this->castType = $castType;
    }

    public function getParentUid(): int
    {
        return $this->parentUid;
    }

    public function setParentUid(int $parentUid): void
    {
        $this->parentUid = $parentUid;
    }

    public function __toString(): string
    {
        return (string)$this->getActualValue();
    }

    protected function castValue(mixed $value, string $type): float|int|string
    {
        settype($value, $type);
        return is_numeric($value) ? $value : 0;
    }


}
