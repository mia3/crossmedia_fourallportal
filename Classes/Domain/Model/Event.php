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
 * Events
 */
class Event extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /**
     * eventId
     *
     * @var int
     */
    protected $eventId = 0;

    /**
     * eventType
     *
     * @var string
     */
    protected $eventType = '';

    /**
     * status
     *
     * @var string
     */
    protected $status = 'pending';

    /**
     * skipUntil
     *
     * @var int
     */
    protected $skipUntil = 0;

    /**
     * objectId
     *
     * @var string
     */
    protected $objectId = '';

    /**
     * module
     *
     * @var \Crossmedia\Fourallportal\Domain\Model\Module
     */
    protected $module = null;

    /**
     * Returns the eventId
     *
     * @return int $eventId
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * Sets the eventId
     *
     * @param int $eventId
     * @return void
     */
    public function setEventId($eventId)
    {
        $this->eventId = $eventId;
    }

    /**
     * Returns the status
     *
     * @return string $status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Sets the status
     *
     * @param string $status
     * @return void
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * Returns the skipUntil
     *
     * @return int $skipUntil
     */
    public function getSkipUntil()
    {
        return $this->skipUntil;
    }

    /**
     * Sets the skipUntil
     *
     * @param int $skipUntil
     * @return void
     */
    public function setSkipUntil($skipUntil)
    {
        $this->skipUntil = $skipUntil;
    }

    /**
     * Returns the objectId
     *
     * @return string $objectId
     */
    public function getObjectId()
    {
        return $this->objectId;
    }

    /**
     * Sets the objectId
     *
     * @param string $objectId
     * @return void
     */
    public function setObjectId($objectId)
    {
        $this->objectId = $objectId;
    }

    /**
     * Returns the module
     *
     * @return \Crossmedia\Fourallportal\Domain\Model\Module $module
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * Sets the module
     *
     * @param \Crossmedia\Fourallportal\Domain\Model\Module $module
     * @return void
     */
    public function setModule(\Crossmedia\Fourallportal\Domain\Model\Module $module)
    {
        $this->module = $module;
    }

    /**
     * Returns the eventType
     *
     * @return string $eventType
     */
    public function getEventType()
    {
        return $this->eventType;
    }

    /**
     * Sets the eventType
     *
     * @param string $eventType
     * @return void
     */
    public function setEventType($eventType)
    {
        $this->eventType = $eventType;
    }

    /**
     * @param $eventTypeId
     */
    public static function resolveEventType($eventTypeId)
    {
        $map = [
            0 => 'delte',
            1 => 'update',
            2 => 'create'
        ];
        return $map[$eventTypeId];
    }
}
