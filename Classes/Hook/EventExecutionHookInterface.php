<?php
namespace Crossmedia\Fourallportal\Hook;

use Crossmedia\Fourallportal\Domain\Model\Event;

interface EventExecutionHookInterface
{
    public function postEventExecution(iterable $events): void;
    public function postSingleManualEventExecution(Event $event): void;
}
