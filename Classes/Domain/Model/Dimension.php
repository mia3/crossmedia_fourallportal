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
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\Mapping\MappingInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Module
 */
class Dimension extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $value = '';

    /**
     * server
     *
     * @var \Crossmedia\Fourallportal\Domain\Model\DimensionMapping
     */
    protected $mapping = null;

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
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * @return DimensionMapping
     */
    public function getMapping()
    {
        return $this->mapping;
    }

    /**
     * @param DimensionMapping $mapping
     */
    public function setMapping($mapping)
    {
        $this->mapping = $mapping;
    }
}
