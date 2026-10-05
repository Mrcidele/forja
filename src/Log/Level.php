<?php

declare(strict_types=1);

namespace Forja\Log;

use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

/**
 * Níveis de log da PSR-3 com severidade numérica (RFC 5424) para comparação.
 */
enum Level: int
{
    case Debug = 100;
    case Info = 200;
    case Notice = 250;
    case Warning = 300;
    case Error = 400;
    case Critical = 500;
    case Alert = 550;
    case Emergency = 600;

    public static function fromName(string $name): self
    {
        return match (strtolower($name)) {
            LogLevel::DEBUG => self::Debug,
            LogLevel::INFO => self::Info,
            LogLevel::NOTICE => self::Notice,
            LogLevel::WARNING => self::Warning,
            LogLevel::ERROR => self::Error,
            LogLevel::CRITICAL => self::Critical,
            LogLevel::ALERT => self::Alert,
            LogLevel::EMERGENCY => self::Emergency,
            default => throw new InvalidArgumentException(sprintf('Nível de log desconhecido: [%s].', $name)),
        };
    }

    public function label(): string
    {
        return strtoupper($this->name);
    }

    public function includes(self $level): bool
    {
        return $level->value >= $this->value;
    }
}
