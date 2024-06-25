<?php
declare(strict_types=1);

namespace Crossmedia\Fourallportal\Response;

use Symfony\Component\Console\Command\Command;

//use TYPO3\CMS\Extbase\Mvc\Cli\Response;

/**
 * @see .build/vendor/typo3/cms-core/Documentation/Changelog/10.0/Breaking-87193-DeprecatedFunctionalityRemoved.rst
 */
class CollectingResponse extends Command
{
  /**
   * @var string
   */
  protected string $collected = '';

  public function send(): void
  {
    $this->collected .= $this->getDescription();
  }

  /**
   * @return string
   */
  public function getCollected(): string
  {
    return $this->collected;
  }
}
