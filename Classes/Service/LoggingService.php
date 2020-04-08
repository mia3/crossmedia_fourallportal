<?php
namespace Crossmedia\Fourallportal\Service;

use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\LogEntry;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LoggingService implements SingletonInterface
{
    const LOG_BASEDIR = 'typo3temp/var/logs/fourallportal/';
    const TYPE_EVENT = 'event';
    const TYPE_OBJECT = 'object';
    const TYPE_FILE = 'file';
    const TYPE_SCHEMA = 'schema';
    const TYPE_CONNECTION = 'connection';
    const TYPE_ERRORS = 'error';

    public function logFileTransferActivity(string $url, string $localFileName, int $severity = GeneralUtility::SYSLOG_SEVERITY_INFO): void
    {
        $logFile = $this->resolveLogFilePath(static::TYPE_FILE);
        $this->writeEntry($logFile, $url . ' ' . $localFileName, $severity);
    }

    /**
     * @param int $numberOfEntries
     * @return iterable|LogEntry[]
     */
    public function getFileTransferActivity(int $numberOfEntries = 0): iterable
    {
        $logFile = $this->resolveLogFilePath(static::TYPE_FILE);
        return $this->getEntries($logFile, $numberOfEntries);
    }

    public function logConnectionActivity(string $message, int $severity = GeneralUtility::SYSLOG_SEVERITY_INFO): void
    {
        $logFile = $this->resolveLogFilePath(static::TYPE_CONNECTION);
        $this->writeEntry($logFile, $message, $severity);
    }

    /**
     * @param int $numberOfEntries
     * @return iterable|LogEntry[]
     */
    public function getConnectionActivity(int $numberOfEntries): iterable
    {
        $logFile = $this->resolveLogFilePath(static::TYPE_CONNECTION);
        return $this->getEntries($logFile, $numberOfEntries);
    }

    public function logEventActivity(Event $event, string $message, int $severity = GeneralUtility::SYSLOG_SEVERITY_INFO): void
    {
        $logFile = $this->resolveLogFilePath(static::TYPE_EVENT, $event->getEventId());
        $this->writeEntry($logFile, $message, $severity);
    }

    /**
     * @param Event $event
     * @param int $numberOfEntries
     * @return iterable|LogEntry[]
     */
    public function getEventActivity(Event $event, int $numberOfEntries = 0): iterable
    {
        $logFile = $this->resolveLogFilePath(static::TYPE_EVENT, $event->getEventId());
        return $this->getEntries($logFile, $numberOfEntries);
    }

    public function logObjectActivity(string $uuid, string $message, string $property, int $severity = GeneralUtility::SYSLOG_SEVERITY_INFO): void
    {
        $logFile = $this->resolveLogFilePath(static::TYPE_OBJECT, $uuid);
        $this->writeEntry($logFile, $property . ' ' . $message, $severity);
    }

    /**
     * @param string $uuid
     * @param int $numberOfEntries
     * @return iterable|LogEntry[]
     */
    public function getObjectActivity(string $uuid, int $numberOfEntries = 0): iterable
    {
        $logFile = $this->resolveLogFilePath(static::TYPE_OBJECT, $uuid);
        return $this->getEntries($logFile, $numberOfEntries);
    }

    public function logSchemaActivity(string $message, int $severity = GeneralUtility::SYSLOG_SEVERITY_INFO): void
    {
        $logFile = $this->resolveLogFilePath(static::TYPE_SCHEMA);
        $this->writeEntry($logFile, $message, $severity);
    }

    /**
     * @param int $numberOfEntries
     * @return iterable|LogEntry[]
     */
    public function getSchemaActivity(int $numberOfEntries = 0): iterable
    {
        $logFile = $this->resolveLogFilePath(static::TYPE_SCHEMA);
        return $this->getEntries($logFile, $numberOfEntries);
    }

    /**
     * @param int $numberOfEntries
     * @return iterable|LogEntry[]
     */
    public function getErrorActivity(int $numberOfEntries = 0): iterable
    {
        $logFile = $this->resolveLogFilePath(static::TYPE_ERRORS);
        return $this->getEntries($logFile, $numberOfEntries);
    }

    /**
     * @param string $logFile
     * @param int $numberOfEntries
     * @return iterable|LogEntry[]
     */
    protected function getEntries(string $logFile, int $numberOfEntries): iterable
    {
        if (!file_exists($logFile)) {
            return [];
        }
        if (!$numberOfEntries) {
            $contents = file_get_contents($logFile);
        } else {
            $contents = shell_exec('tail -n ' . $numberOfEntries . ' ' . $logFile);
        }
        $entries = explode(PHP_EOL, trim($contents));
        $items = [];
        foreach (array_reverse($entries) as $entry) {
            [$date, $severity, $message] = explode(' ', $entry, 3);
            $items[] = GeneralUtility::makeInstance(LogEntry::class, $date, (int) $severity, $message);
        }
        return $items;
    }

    protected function writeEntry(string $logFile, string $message, int $severity): void
    {
        $fp = fopen($logFile, 'a+');
        fwrite($fp, date('Y-m-d_H:i:s') . ' ' . $severity . ' ' . $message . PHP_EOL);
        fclose($fp);
        if ($severity >= GeneralUtility::SYSLOG_SEVERITY_WARNING) {
            $fp = fopen($this->resolveLogFilePath(static::TYPE_ERRORS), 'a+');
            fwrite($fp, date('Y-m-d_H:i:s') . ' ' . $severity . ' ' . $message . PHP_EOL);
            fclose($fp);
        }
    }

    protected function resolveLogFilePath(string $type, string $identity = null): string
    {
        $logFilePath = static::LOG_BASEDIR . $type;
        if ($identity) {
            $logFilePath .= '/' . $identity . '.log';
        } else {
            $logFilePath .= '.log';
        }
        $path = pathinfo($logFilePath, PATHINFO_DIRNAME);
        @mkdir($path, 0755, true);
        return GeneralUtility::getFileAbsFileName($logFilePath);
    }
}
