<?php
namespace Crossmedia\Fourallportal\Mapping;

use Crossmedia\Fourallportal\Domain\Model\Event;

interface MappingInterface
{
    public function import(array $data, Event $event);
}