<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Service\ConfigGeneratorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception;

#[AsCommand(
  name: 'fourallportal:generateTableConfiguration',
  description: 'Generate TCA for model'
)]
class GenerateTableConfigurationCommand extends Command
{

  public function __construct(
    protected ?ConfigGeneratorService $configGeneratorService = null
  )
  {
    parent::__construct();
  }

  protected function configure()
  {
    $this
      ->setDescription('Generate TCA for model')
      ->setHelp("This command can be used instead or together with the\ndynamic model feature to generate a TCA file for a particular\nentity, by its class name.\n\nInternally the class name is analysed to determine the\nextension it belongs to, and makes an assumption about the\ntable name. The command then writes the generated TCA to the\nexact TCA configuration file (by filename convention) and\nwill overwrite any existing TCA in that file.\n\nShould you need to adapt individual properties such as the\nfield used for label, the icon path etc. please use the\nConfiguration/TCA/Overrides/\$tableName.php file instead.")
      ->addArgument('entityClassName', InputArgument::REQUIRED, 'entityClassName')
      ->addArgument('readOnly', InputArgument::OPTIONAL, 'If TRUE, generates TCA fields as read-only', false);
  }


  /**
   * Generate TCA for model
   *
   * This command can be used instead or together with the
   * dynamic model feature to generate a TCA file for a particular
   * entity, by its class name.
   *
   * Internally the class name is analysed to determine the
   * extension it belongs to, and makes an assumption about the
   * table name. The command then writes the generated TCA to the
   * exact TCA configuration file (by filename convention) and
   * will overwrite any existing TCA in that file.
   *
   * Should you need to adapt individual properties such as the
   * field used for label, the icon path etc. please use the
   * Configuration/TCA/Overrides/$tableName.php file instead.
   *
   * @param InputInterface $input
   * @param OutputInterface $output
   * @return int
   * @throws Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $io = new SymfonyStyle($input, $output);
    $io->title($this->getDescription());

    $entityClassName = (string)$input->getArgument('entityClassName');
    $readOnly = (bool)$input->getArgument('readOnly');

    $this->configGeneratorService->generateTableConfiguration($entityClassName, $readOnly);
    return Command::SUCCESS;
  }

}