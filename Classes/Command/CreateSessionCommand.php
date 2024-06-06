<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\Domain\Repository\ServerRepository;
use Crossmedia\Fourallportal\DynamicModel\DynamicModelGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsCommand(
  name: 'fourallportal:createSession'
)]
class CreateSessionCommand extends Command
{

  public function __construct(
    protected ?DynamicModelGenerator $dynamicModelGenerator = null
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
      ->setDescription('Updates local model classes with properties as specified by the mapping information and model information from the API. Uses the Server and Module configurations in the system and consults the Mapping class to identify each model that must be updated, then uses the DynamicModelHandler to generate an abstract model class to use with each specific model');
  }

  /**
   * Create session ID
   *
   * Logs in on the specified server (or active server) and
   * outputs the session ID, which can then be used for testing
   * in for example raw CURL requests.
   *
   * @param int $server
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $server = 0; // TODO: get from user input
//    if ($server === 0) {
//      /** @var Server $server */
//      $server = $this->serverRepository->findOneByActive(true);
//    } else {
//      /** @var Server $server */
//      $server = $this->serverRepository->findByUid($server);
//    }
//    $sessionId = $this->apiClient->login();
//    $this->response->setContent($sessionId . PHP_EOL);
//    $this->response->send();
    return Command::SUCCESS;
  }

}