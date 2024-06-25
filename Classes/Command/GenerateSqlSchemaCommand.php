<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Service\ConfigGeneratorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'fourallportal:generateSqlSchema',
  description: 'Generate additional SQL schema file'
)]
class GenerateSqlSchemaCommand extends Command
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
      ->setDescription('Generate additional SQL schema file')
      ->setHelp("Generate additional SQL schema file\n\nThis command can be used as substitute for the automatic\nSQL schema generation - using it disables the analysis of\nthe Module to read schema properties. If used, should be\ncombined with both of the other \"generate\" commands from\nthis package, to create a completely static set of assets\nbased on the configured Modules and prevent dynamic changes.\n\nGenerates all schemas for all modules, and generates a static\nSQL schema file in the extension to which the entity belongs.\nThe SQL schema registration hook then circumvents the normal\nschema fetching and uses the static schema instead, when the\nextension has a static schema.");
  }

  /**
   * Generate additional SQL schema file
   *
   * This command can be used as substitute for the automatic
   * SQL schema generation - using it disables the analysis of
   * the Module to read schema properties. If used, should be
   * combined with both of the other "generate" commands from
   * this package, to create a completely static set of assets
   * based on the configured Modules and prevent dynamic changes.
   *
   * Generates all schemas for all modules, and generates a static
   * SQL schema file in the extension to which the entity belongs.
   * The SQL schema registration hook then circumvents the normal
   * schema fetching and uses the static schema instead, when the
   * extension has a static schema.
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->configGeneratorService->generateSqlSchemaCommand();
    return Command::SUCCESS;
  }

}