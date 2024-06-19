<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Service\ConfigGeneratorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

#[AsCommand(
  name: 'fourallportal:generateAbstractModelClass',
  description: 'Generate abstract entity class'
)]
class GenerateAbstractModelClassCommand extends Command
{

  public function __construct(
    protected ?ConfigGeneratorService $configGeneratorService = null
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
      ->setDescription('Generate abstract entity class')
      ->setHelp("Generate abstract entity class\n\nThis command can be used as substitute for the automatic\nmodel class generation feature. Each entity class generated\nwith this command prevents usage of the dynamically created\nclass (which still gets created!). To re-enable dynamic\noperation simply remove the generated abstract class again.\n\nGenerates an abstract PHP class in the same namespace as\nthe input entity class name. The abstract class contains\nall the dynamically generated properties associated with\nthe Module.")
      ->addArgument('entityClassName', InputArgument::REQUIRED, 'entityClassName')
      ->addArgument('strict', InputArgument::OPTIONAL, 'If TRUE, generates strict PHP code', false);
  }

  /**
   * Generate abstract entity class
   *
   * This command can be used as substitute for the automatic
   * model class generation feature. Each entity class generated
   * with this command prevents usage of the dynamically created
   * class (which still gets created!). To re-enable dynamic
   * operation simply remove the generated abstract class again.
   *
   * Generates an abstract PHP class in the same namespace as
   * the input entity class name. The abstract class contains
   * all the dynamically generated properties associated with
   * the Module.
   *
   * @param InputInterface $input
   * @param OutputInterface $output
   * @return int
   * @throws ApiException
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $io = new SymfonyStyle($input, $output);
    $io->title($this->getDescription());

    $entityClassName = (string)$input->getArgument('entityClassName');
    $strict = (bool)$input->getArgument('strict');

    $this->configGeneratorService->generateAbstractModelClassCommand($io, $entityClassName, $strict);
    return Command::SUCCESS;
  }

}