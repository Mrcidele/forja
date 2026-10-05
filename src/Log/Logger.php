<?php

declare(strict_types=1);

namespace Forja\Log;

use DateTimeImmutable;
use DateTimeInterface;
use Forja\Log\Handler\HandlerInterface;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Logger PSR-3: interpola {placeholders} com o contexto e repassa o registro
 * aos handlers que aceitam o nível.
 */
final class Logger extends AbstractLogger
{
    /**
     * @param list<HandlerInterface> $handlers
     */
    public function __construct(
        private readonly string $channel = 'app',
        private array $handlers = [],
    ) {
    }

    public function pushHandler(HandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * @param array<mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $level = $level instanceof Level ? $level : Level::fromName(is_string($level) ? $level : '');
        $record = null;

        foreach ($this->handlers as $handler) {
            if (! $handler->handles($level)) {
                continue;
            }

            $record ??= new LogRecord(new DateTimeImmutable(), $this->channel, $level, $this->interpolate((string) $message, $context), $context);
            $handler->handle($record);
        }
    }

    /**
     * @param array<mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        if (! str_contains($message, '{')) {
            return $message;
        }

        $replacements = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null || $value instanceof Stringable) {
                $replacements['{' . $key . '}'] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            } elseif ($value instanceof DateTimeInterface) {
                $replacements['{' . $key . '}'] = $value->format(DATE_ATOM);
            } elseif (is_object($value)) {
                $replacements['{' . $key . '}'] = '[objeto ' . $value::class . ']';
            } else {
                $replacements['{' . $key . '}'] = '[' . get_debug_type($value) . ']';
            }
        }

        return strtr($message, $replacements);
    }
}
