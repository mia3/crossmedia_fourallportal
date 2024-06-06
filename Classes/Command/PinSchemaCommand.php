<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

#[AsCommand(
  name: 'fourallportal:pinschema'
)]
class PinSchemaCommand extends Command
{

  public function __construct(
    protected ?ModuleRepository   $moduleRepository = null,
    protected ?PersistenceManager $persistenceManager = null,
  )
  {
    parent::__construct();
  }

  /**
   * Configure the command by defining the name, options and arguments
   */
  protected function configure()
  {
    $this
      ->setDescription('Pins the PIM schema version, updating all local modules to use the version of configuration that is currently live on the configured remote server.')
      ->setHelp('Used when a schema version mismatch prevents PIM sync from running.');
  }

  /**
   * Pin PIM schema version
   *
   * Pins the PIM schema version, updating all local modules to use the
   * version of configuration that is currently live on the configured
   * remote server.
   *
   * Used when a schema version mismatch prevents PIM sync from running.
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    foreach ($this->moduleRepository->findAll() as $module) {
      if ($module->getServer()->isActive()) {
        $module->pinSchemaVersion();
      }
    }
    $this->persistenceManager->persistAll();
    return Command::SUCCESS;
  }
}