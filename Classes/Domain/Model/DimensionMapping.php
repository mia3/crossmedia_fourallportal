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
class DimensionMapping extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /**
     * @var int
     */
    protected $language = '';

    /**
     * server
     *
     * @var \Crossmedia\Fourallportal\Domain\Model\Server
     */
    protected $server = null;

    /**
     * modules
     *
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\Crossmedia\Fourallportal\Domain\Model\Dimension>
     * @cascade remove
     */
    protected $dimensions = null;

    /**
     * @return int
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @param int $language
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }

    /**
     * @return Server
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @param Server $server
     */
    public function setServer($server)
    {
        $this->server = $server;
    }

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage $dimensions
     */
    public function setDimensions($dimensions)
    {
        $this->dimensions = $dimensions;
    }

    public function matches($dimensions) {
        foreach ($this->dimensions as $dimension) {
            if ($dimensions[$dimension->getName()] != $dimension->getValue()) {
                return false;
            }
        }
        return true;
    }
}
