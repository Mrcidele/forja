<?php

declare(strict_types=1);

namespace Forja\Log;

use DateTimeImmutable;

/**
 * Uma entrada de log já com a mensagem interpolada.
 */
final readonly class LogRecord
{
    /**
     * @param array<mixed> $context
     */
    public function __construct(
        public DateTimeImmutable $datetime,
        public string $channel,
        public Level $level,
        public string $message,
        public array $context = [],
    ) {
    }
}
