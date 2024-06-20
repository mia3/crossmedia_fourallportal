<?php

namespace Crossmedia\Products\Mapping;

use Crossmedia\Fourallportal\Mapping\AbstractMapping;
use Crossmedia\Products\Domain\Repository\EasyRepository;

class EasyMapping extends AbstractMapping
{
  protected string $repositoryClassName = EasyRepository::class;


}
