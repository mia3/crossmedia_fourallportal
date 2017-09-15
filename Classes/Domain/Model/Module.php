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
use Crossmedia\Fourallportal\Mapping\MappingInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Module
 */
class Module extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /**
     * connectorName
     *
     * @var string
     */
    protected $connectorName = '';

    /**
     * mappingClass
     *
     * @var string
     */
    protected $mappingClass = '';

    /**
     * configHash
     *
     * @var string
     */
    protected $configHash = '';

    /**
     * lastEventId
     *
     * @var int
     */
    protected $lastEventId = 0;

    /**
     * shellPath
     *
     * @var string
     */
    protected $shellPath = '';

    /**
     * storagePid
     *
     * @var int
     */
    protected $storagePid = 0;

    /**
     * @var int
     */
    protected $falStorage = 0;

    /**
     * server
     *
     * @var \Crossmedia\Fourallportal\Domain\Model\Server
     */
    protected $server = null;

    /**
     * Returns the connectorName
     *
     * @return string $connectorName
     */
    public function getConnectorName()
    {
        return $this->connectorName;
    }

    /**
     * Sets the connectorName
     *
     * @param string $connectorName
     * @return void
     */
    public function setConnectorName($connectorName)
    {
        $this->connectorName = $connectorName;
    }

    /**
     * Returns the configHash
     *
     * @return string $configHash
     */
    public function getConfigHash()
    {
        return $this->configHash;
    }

    /**
     * Sets the configHash
     *
     * @param string $configHash
     * @return void
     */
    public function setConfigHash($configHash)
    {
        $this->configHash = $configHash;
    }

    /**
     * Returns the lastEventId
     *
     * @return int $lastEventId
     */
    public function getLastEventId()
    {
        return $this->lastEventId;
    }

    /**
     * Sets the lastEventId
     *
     * @param int $lastEventId
     * @return void
     */
    public function setLastEventId($lastEventId)
    {
        $this->lastEventId = $lastEventId;
    }

    /**
     * Returns the server
     *
     * @return \Crossmedia\Fourallportal\Domain\Model\Server server
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * Sets the server
     *
     * @param string $server
     * @return void
     */
    public function setServer($server)
    {
        $this->server = $server;
    }

    /**
     * Returns the shellPath
     *
     * @return string $shellPath
     */
    public function getShellPath()
    {
        return $this->shellPath;
    }

    /**
     * Sets the shellPath
     *
     * @param string $shellPath
     * @return void
     */
    public function setShellPath($shellPath)
    {
        $this->shellPath = $shellPath;
    }

    /**
     * Returns the storagePid
     *
     * @return int $storagePid
     */
    public function getStoragePid()
    {
        return $this->storagePid;
    }

    /**
     * Sets the storagePid
     *
     * @param int $storagePid
     * @return void
     */
    public function setStoragePid($storagePid)
    {
        $this->storagePid = $storagePid;
    }

    /**
     * @return int
     */
    public function getFalStorage()
    {
        return $this->falStorage;
    }

    /**
     * @param int $falStorage
     */
    public function setFalStorage($falStorage)
    {
        $this->falStorage = $falStorage;
    }

    /**
     * @return MappingInterface
     */
    public function getMapper()
    {
        return GeneralUtility::makeInstance(ObjectManager::class)->get($this->getMappingClass());
    }

    /**
     * Returns the mappingClass
     *
     * @return string mappingClass
     */
    public function getMappingClass()
    {
        return $this->mappingClass;
    }

    /**
     * Sets the mappingClass
     *
     * @param string $mappingClass
     * @return void
     */
    public function setMappingClass($mappingClass)
    {
        $this->mappingClass = $mappingClass;
    }
}
