<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Domain\Repository\ServerRepository;
use Crossmedia\Fourallportal\Error\ApiException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
  name: 'fourallportal:createSession'
)]
class CreateSessionCommand extends Command
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
      ->setDescription('Logs in on the specified server (or active server) and outputs the session ID, which can then be used for testing in for example raw CURL requests.')
      ->addArgument(
        'server', InputArgument::REQUIRED, 'server id'
      );
  }

  /**
   * Create session ID
   *
   * Logs in on the specified server (or active server) and
   * outputs the session ID, which can then be used for testing
   * in for example raw CURL requests.
   *
   * @param InputInterface $input
   * @param OutputInterface $output
   * @return int
   * @throws ApiException
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $io = new SymfonyStyle($input, $output);
    $io->title($this->getDescription());
    $serverId = (int)$input->getArgument('server');

    if ($serverId === 0) {
      /** @var Server $server */
      $server = $this->serverRepository->findOneByActive(true);
    } else {
      /** @var Server $server */
      $server = $this->serverRepository->findByUid($serverId);
    }
    if ($server !== null) {
      $sessionId = $server->getClient()->login();
      $io->writeln($sessionId . PHP_EOL);
      return Command::SUCCESS;
    }
    return Command::FAILURE;
  }

}