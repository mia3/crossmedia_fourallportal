<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Domain\Repository\ServerRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
  name: 'fourallportal:getConfiguration'
)]
class GetConfigurationCommand extends Command
{

  public function __construct(
    protected ?ServerRepository $serverRepository = null
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
      ->setDescription('Gets the module and connector configuration for the module identified by $moduleName, and outputs it as JSON.')
      ->addArgument(
        'moduleName', InputArgument::REQUIRED, 'module name'
      )
      ->addArgument(
        'server', InputArgument::REQUIRED, 'server id'
      );
  }


  /**
   * Get module and connector configuration
   *
   * Gets the module and connector configuration for the module
   * identified by $moduleName, and outputs it as JSON.
   *
   * @param string $moduleName Name of module for which to get configuration
   * @param int $server Optional UID of server, defaults to active server
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $io = new SymfonyStyle($input, $output);
    $io->title($this->getDescription());
    $serverId = (int)$input->getArgument('server');
    $moduleName = (string)$input->getArgument('moduleName');

    if ($serverId === 0) {
      /** @var Server $server */
      $server = $this->serverRepository->findOneByActive(true);
    } else {
      /** @var Server $server */
      $server = $this->serverRepository->findByUid($serverId);
    }
    $module = $server->getModule($moduleName);
    $io->writeln(json_encode($module->getModuleConfiguration(), JSON_PRETTY_PRINT));
    $io->writeln(PHP_EOL);
    $io->writeln(json_encode($module->getConnectorConfiguration(), JSON_PRETTY_PRINT));
  }

}