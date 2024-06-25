<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\DynamicModel\DynamicModelGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'fourallportal:updateModels',
  description: 'Update models'
)]
class UpdateModelsCommand extends Command
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
      ->setDescription('Update models')
      ->setHelp("Updates local model classes with properties as specified by\nthe mapping information and model information from the API.\nUses the Server and Module configurations in the system and\nconsults the Mapping class to identify each model that must\nbe updated, then uses the DynamicModelHandler to generate\nan abstract model class to use with each specific model.\n\nA special class loading function must be used in the model\nbefore it can use the dynamically generated base class. See\nthe provided README.md file for more information about this.");
  }

  /**
   * Update models
   *
   * Updates local model classes with properties as specified by
   * the mapping information and model information from the API.
   * Uses the Server and Module configurations in the system and
   * consults the Mapping class to identify each model that must
   * be updated, then uses the DynamicModelHandler to generate
   * an abstract model class to use with each specific model.
   *
   * A special class loading function must be used in the model
   * before it can use the dynamically generated base class. See
   * the provided README.md file for more information about this.
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->dynamicModelGenerator->generateAbstractModelsForAllModules();
    return Command::SUCCESS;
  }

}