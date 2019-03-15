<?php
namespace Crossmedia\Fourallportal\Domain\Dto;

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
 * Parameters for executing a sync
 */
class SyncParameters
{
    protected $sync = true;
    protected $fullSync = false;
    protected $force = false;
    protected $execute = true;
    protected $module = null;
    protected $exclude = null;
    protected $timeLimit = 0;
    protected $eventLimit = 0;

    private $beganTime = 0;
    private $eventsExecuted = 0;

    public function shouldContinue(): bool
    {
        if ($this->timeLimit > 0 && (time() - $this->beganTime) >= $this->timeLimit) {
            return false;
        }
        if ($this->eventLimit > 0 && $this->eventsExecuted >= $this->eventLimit) {
            return false;
        }
        return true;
    }

    public function startExecution(): self
    {
        $this->beganTime = time();
        return $this;
    }

    public function countExecutedEvent(): int
    {
        return ++ $this->eventsExecuted;
    }

    public function getFullSync(): bool
    {
        return $this->fullSync;
    }

    public function setFullSync(bool $fullSync): self
    {
        $this->fullSync = $fullSync;
        return $this;
    }

    public function getSync(): bool
    {
        return $this->sync;
    }

    public function setSync(bool $sync): self
    {
        $this->sync = $sync;
        return $this;
    }

    public function getForce(): bool
    {
        return $this->force;
    }

    public function setForce(bool $force): self
    {
        $this->force = $force;
        return $this;
    }

    public function getExecute(): bool
    {
        return $this->execute;
    }

    public function setExecute(bool $execute): self
    {
        $this->execute = $execute;
        return $this;
    }

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function setModule($module): self
    {
        $this->module = $module;
        return $this;
    }

    public function getExclude(): ?string
    {
        return $this->exclude;
    }

    public function setExclude($exclude): self
    {
        $this->exclude = $exclude;
        return $this;
    }

    public function getTimeLimit(): int
    {
        return $this->timeLimit;
    }

    public function setTimeLimit(int $timeLimit): self
    {
        $this->timeLimit = $timeLimit;
        return $this;
    }

    public function getEventLimit(): int
    {
        return $this->eventLimit;
    }

    public function setEventLimit(int $eventLimit): self
    {
        $this->eventLimit = $eventLimit;
        return $this;
    }
}
