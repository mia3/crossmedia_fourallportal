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
 * Module
 */
class Dimension extends AbstractEntity
{
    protected string $name = '';
    protected string $value = '';
    protected ?DimensionMapping $mapping = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getMapping(): ?DimensionMapping
    {
        return $this->mapping;
    }

    public function setMapping(DimensionMapping $mapping): void
    {
        $this->mapping = $mapping;
    }
}
