<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\Domain\Repository\ServerRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsCommand(
  name: 'fourallportal:test'
)]
class TestCommand extends Command
{

  public function __construct(
    protected ?ServerRepository $serverRepository = null,
    protected ?ModuleRepository $moduleRepository = null
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
      ->setDescription('Runs tests on schema and response consistency and performs tracking of basic response changes, i.e. simple diffs of which properties are included in the response.')
      ->addArgument(
        'onlyFailed',
        InputArgument::OPTIONAL,
        'If TRUE, only outputs failed properties'
      )->addArgument(
        'withHistory',
        InputArgument::OPTIONAL,
        'If TRUE, includes a tracking history of schema/response consistency for each module'
      );
  }

  /**
   * Run tests
   *
   * Runs tests on schema and response consistency and performs tracking
   * of basic response changes, i.e. simple diffs of which properties
   * are included in the response.
   *
   * Outputs streaming YAML.
   *
   * @param bool $onlyFailed If TRUE, only outputs failed properties.
   * @param bool $withHistory If TRUE, includes a tracking history of schema/response consistency for each module.
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $io = new SymfonyStyle($input, $output);
    $io->title($this->getDescription());
    $onlyFailed = $input->getArgument('onlyFailed');
    $withHistory = $input->getArgument('withHistory');

    foreach ($this->moduleRepository->findAll() as $module) {
      $testObjectUuid = $module->getTestObjectUuid();
      $content = $module->getModuleName() . ':';
      if (empty($testObjectUuid)) {
        $content .= ' false';
        continue;
      }
      $content .= PHP_EOL;
      $content .= '  fields:' . PHP_EOL;
      $io->write($content); //  = send
      $bean = $module->getServer()->getClient()->getBeans($testObjectUuid, $module->getConnectorName());
      $fieldsToLoad = $module->getConnectorConfiguration()['fieldsToLoad'];
      foreach ($fieldsToLoad as $fieldName => $configuration) {
        $content = '    ' . $fieldName . ':';
        if (isset($bean['info']['not_accessible_ids'])) {
          $content .= ' "Not found: ' . $testObjectUuid . '"' . PHP_EOL;
          $io->write($content); //  = send
        } elseif (array_key_exists($fieldName, $bean['result'][0]['properties'] ?? [])) {
          if (!$onlyFailed) {
            $content .= ' true' . PHP_EOL;
            $io->write($content); //  = send
          }
        } else {
          $content .= ' false' . PHP_EOL;
          $io->write($content); //  = send
        }
      }
      if ($withHistory && isset($bean['result'][0]['properties'])) {
        $this->trackHistory($module, $bean['result'][0]['properties']);
        $history = $this->getModuleHistory($module);
        $content = '  history:';
        if (empty($history)) {
          $content .= ' false' . PHP_EOL;
          $io->write($content); //  = send
          continue;
        }
        $content .= PHP_EOL;
        $io->write($content); //  = send
        foreach ($history as $date => list (, $addedLoad, $removedLoad, , $addedProperties, $removedProperties)) {
          $touched = false;
          $content = '    ' . $date . ':' . PHP_EOL;
          if (!empty($addedLoad)) {
            $content .= '      - addedFieldsToLoad: ["' . implode('", "', $addedLoad) . '"]' . PHP_EOL;
            $touched = true;
          }
          if (!empty($removedLoad)) {
            $content .= '      - removedFieldsToLoad: ["' . implode('", "', $removedLoad) . '"]' . PHP_EOL;
            $touched = true;
          }
          if (!empty($addedProperties)) {
            $content .= '      - addedProperties: ["' . implode('", "', $addedProperties) . '"]' . PHP_EOL;
            $touched = true;
          }
          if (!empty($removedProperties)) {
            $content .= '      - removedProperties: ["' . implode('", "', $removedProperties) . '"]' . PHP_EOL;
            $touched = true;
          }
          if ($touched) {
            $io->write($content); //  = send
          }
          $content = '';
        }
      }
    }
    $content .= PHP_EOL;
    $io->write($content); //  = send
    return Command::SUCCESS;
  }

  protected function trackHistory(Module $module, array $properties)
  {
    $history = $this->getModuleHistory($module);
    $fieldsToLoad = $module->getConnectorConfiguration()['fieldsToLoad'];
    $currentFieldsToLoad = array_keys($fieldsToLoad);
    $currentProperties = array_keys($properties);
    $mostRecent = end($history);
    reset($history);
    if (!$mostRecent) {
      $history[date('Ymd_Hi')] = [
        $currentFieldsToLoad,
        [],
        [],
        $currentProperties,
        [],
        []
      ];
    } else {
      list ($mostRecentFieldsToLoad, , , $mostRecentProperties, ,) = $mostRecent;
      if ($mostRecentFieldsToLoad != $currentFieldsToLoad || $mostRecentProperties != $currentProperties) {
        $diffFieldsToLoad = array_diff($mostRecentFieldsToLoad, $currentFieldsToLoad);
        $diffProperties = array_diff($mostRecentProperties, $currentProperties);
        $addedFieldsToLoad = [];
        $removedFieldsToLoad = [];
        foreach ($diffFieldsToLoad as $diffPropertyName) {
          if (!array_key_exists($diffPropertyName, $currentFieldsToLoad)) {
            $addedFieldsToLoad[] = $diffPropertyName;
          } else {
            $removedFieldsToLoad[] = $diffPropertyName;
          }
        }
        $addedProperties = [];
        $removedProperties = [];
        foreach ($diffProperties as $diffPropertyName) {
          if (!array_key_exists($diffPropertyName, $currentProperties)) {
            $addedProperties[] = $diffPropertyName;
          } else {
            $removedProperties[] = $diffPropertyName;
          }
        }
        $history[date('Ymd_Hi')] = [
          $currentFieldsToLoad,
          $addedFieldsToLoad,
          $removedFieldsToLoad,
          $currentProperties,
          $addedProperties,
          $removedProperties
        ];
      }
    }

    $historyFile = $this->getHistoryFilename($module);
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));
  }

  protected function getModuleHistory(Module $module)
  {
    $historyFile = $this->getHistoryFilename($module);
    if (!file_exists($historyFile)) {
      return [];
    }
    return json_decode(file_get_contents($historyFile), true);
  }

  protected function getHistoryFilename(Module $module)
  {
    $historyFilesFolder = 'fileadmin/api_samples/';
    return GeneralUtility::getFileAbsFileName($historyFilesFolder) . $module->getModuleName() . '.json';
  }

}