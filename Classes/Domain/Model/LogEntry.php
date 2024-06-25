<?php

namespace Crossmedia\Fourallportal\Domain\Model;

class LogEntry
{
  protected string $date = '';
  protected int $severity = 0; // INFO
  protected string $message = '';

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
    return match ($this->severity) {
      4, 3, 2 => 'danger',
      default => 'default',
    };
  }
}
