<?php
namespace Crossmedia\Fourallportal\Domain\Model;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class LogEntry
{
    protected $date = '';
    protected $severity = 0;
    protected $message = '';

    public function __construct(string $date, int $severity, string $message)
    {
        $this->date = $date;
        $this->severity = $severity;
        $this->message = $message;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getSeverity(): int
    {
        return $this->severity;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getSeverityClassName(): string
    {
        switch ($this->severity) {
            case GeneralUtility::SYSLOG_SEVERITY_FATAL:
            case GeneralUtility::SYSLOG_SEVERITY_WARNING:
            case GeneralUtility::SYSLOG_SEVERITY_ERROR:
                $className = 'danger';
                break;
            default:
                $className = 'default';
        }
        return $className;
    }
}
