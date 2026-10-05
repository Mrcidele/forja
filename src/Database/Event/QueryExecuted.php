<?php

declare(strict_types=1);

namespace Forja\Database\Event;

/**
 * Disparado após cada consulta SQL.
 */
final readonly class QueryExecuted
{
    /**
     * @param list<mixed> $bindings
     */
    public function __construct(
        public string $sql,
        public array $bindings,
        public float $timeMs,
        public string $driver,
    ) {
    }
}
