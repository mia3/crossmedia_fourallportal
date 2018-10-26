<?php
declare(strict_types=1);
namespace Crossmedia\Fourallportal\Response;

use TYPO3\CMS\Extbase\Mvc\Cli\Response;

class CollectingResponse extends Response
{
    /**
     * @var string
     */
    protected $collected = '';

    public function send()
    {
        $this->collected .= $this->content;
    }

    /**
     * @return string
     */
    public function getCollected(): string
    {
        return $this->collected;
    }
}
