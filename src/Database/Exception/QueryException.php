<?php

declare(strict_types=1);

namespace Forja\Database\Exception;

use PDOException;
use RuntimeException;

/**
 * Falha ao executar SQL, com a consulta e os valores vinculados para depuração.
 */
final class QueryException extends RuntimeException
{
    /**
     * @param list<mixed> $bindings
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $bindings,
        PDOException $previous,
    ) {
        parent::__construct(sprintf('%s (SQL: %s)', $previous->getMessage(), $sql), 0, $previous);
    }
}
