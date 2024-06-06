<?php

declare(strict_types=1);

namespace Crossmedia\Fourallportal\EventListener;

use Crossmedia\Fourallportal\DynamicModel\DynamicModelGenerator;
use TYPO3\CMS\Core\Database\Event\AlterTableDefinitionStatementsEvent;

final class TablesDefinitionListener
{
  public function __construct(protected DynamicModelGenerator $modelGenerator)
  {
  }

  public function __invoke(AlterTableDefinitionStatementsEvent $event): void
  {
    $mergedData = $this->modelGenerator->addSchemasForAllModules($event->getSqlData());
    $event->setSqlData($mergedData);
  }
}