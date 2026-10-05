<?php

declare(strict_types=1);

namespace Forja\Database\Schema;

use Closure;
use Forja\Database\Connection;
use Forja\Database\Schema\Grammar\MySqlSchemaGrammar;
use Forja\Database\Schema\Grammar\PostgresSchemaGrammar;
use Forja\Database\Schema\Grammar\SchemaGrammar;
use Forja\Database\Schema\Grammar\SqliteSchemaGrammar;

/**
 * Cria, altera e remove tabelas de forma independente do banco.
 */
final readonly class Schema
{
    private SchemaGrammar $grammar;

    public function __construct(
        private Connection $connection,
    ) {
        $this->grammar = match ($connection->driver()) {
            'mysql' => new MySqlSchemaGrammar($connection->grammar()),
            'pgsql' => new PostgresSchemaGrammar($connection->grammar()),
            default => new SqliteSchemaGrammar($connection->grammar()),
        };
    }

    /**
     * @param Closure(Blueprint): void $callback
     */
    public function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $this->run($this->grammar->compileCreate($blueprint));
    }

    /**
     * Altera uma tabela existente (adicionar/remover colunas e índices).
     *
     * @param Closure(Blueprint): void $callback
     */
    public function table(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $this->run($this->grammar->compileAlter($blueprint));
    }

    public function drop(string $table): void
    {
        $this->connection->statement($this->grammar->compileDrop($table));
    }

    public function dropIfExists(string $table): void
    {
        $this->connection->statement($this->grammar->compileDropIfExists($table));
    }

    public function rename(string $from, string $to): void
    {
        $this->connection->statement($this->grammar->compileRename($from, $to));
    }

    public function hasTable(string $table): bool
    {
        [$sql, $bindings] = $this->grammar->compileHasTable($table);

        $count = $this->connection->scalar($sql, $bindings);

        return is_numeric($count) && (int) $count > 0;
    }

    public function grammar(): SchemaGrammar
    {
        return $this->grammar;
    }

    /**
     * @param list<string> $statements
     */
    private function run(array $statements): void
    {
        foreach ($statements as $sql) {
            $this->connection->statement($sql);
        }
    }
}
