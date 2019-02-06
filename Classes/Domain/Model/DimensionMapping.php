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
     * @var string
     */
    protected $metricOrImperial = 'Metric';

    /**
     * active
     *
     * @var bool
     */
    protected $active = false;

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

    /**
     * @return string
     */
    public function getMetricOrImperial()
    {
        return $this->metricOrImperial;
    }

    /**
     * @param string $metricOrImperial
     */
    public function setMetricOrImperial($metricOrImperial)
    {
        $this->metricOrImperial = $metricOrImperial;
    }

    /**
     * Returns the active
     *
     * @return bool $active
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Sets the active
     *
     * @param bool $active
     * @return void
     */
    public function setActive($active)
    {
        $this->active = $active;
    }

    /**
     * Returns the boolean state of active
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * Returns $this if the current object is the default dimension;
     * or behaves as an emulated 1:1 relation to a default language.
     *
     * @return DimensionMapping
     */
    public function getDefaultDimensionMapping(): DimensionMapping
    {
        if ($this->language === 0) {
            return $this;
        }

        foreach ($this->server->getDimensionMappings() as $dimensionMapping) {
            if ($dimensionMapping->getLanguage() === 0) {
                return $dimensionMapping;
            }
        }

        // TODO: throw a deferral if the resolved value is null
        return null;
    }

    public function matches($dimensions) {
        foreach ($this->dimensions as $dimension) {
            if (!isset($dimensions[$dimension->getName()])) {
                // We will allow and ignore the case of a requested locale not being present in the PIM data.
                // Technically this constitutes an error. Thus we throw this little member-berry in here:
                // TODO: throw an exception once the data on PIM is consistent, to report such a problem as an error.
                continue;
            }
            if ($dimensions[$dimension->getName()] != $dimension->getValue()) {
                return false;
            }
        }
        return true;
    }
}
