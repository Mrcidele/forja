<?php

declare(strict_types=1);

namespace Forja\Database\Schema\Grammar;

use Forja\Database\Expression;
use Forja\Database\Query\Grammar\Grammar;
use Forja\Database\Schema\Blueprint;
use Forja\Database\Schema\ColumnDefinition;
use InvalidArgumentException;

/**
 * Gera DDL a partir de um Blueprint. Cada banco define os tipos e detalhes de sintaxe.
 */
abstract class SchemaGrammar
{
    public function __construct(
        protected readonly Grammar $grammar,
    ) {
    }

    /**
     * Tipo SQL de uma coluna (sem modificadores).
     */
    abstract protected function type(ColumnDefinition $column): string;

    /**
     * Definição completa de uma chave primária autoincremento.
     */
    abstract protected function autoIncrementPrimaryKey(): string;

    /**
     * @return array{string, list<mixed>}
     */
    abstract public function compileHasTable(string $table): array;

    /**
     * @return list<string>
     */
    public function compileCreate(Blueprint $blueprint): array
    {
        $definitions = array_map($this->columnDefinition(...), $blueprint->columns());

        foreach ($blueprint->indexes() as $index) {
            if ($index['type'] === 'primary') {
                $definitions[] = 'primary key (' . $this->columnize($index['columns']) . ')';
            }
        }

        foreach ($blueprint->columns() as $column) {
            if ($column->references !== null) {
                $definitions[] = 'foreign key (' . $this->grammar->wrap($column->name) . ') ' . $this->referencesClause($column);
            }
        }

        return [
            sprintf('create table %s (%s)', $this->grammar->wrap($blueprint->table), implode(', ', $definitions)),
            ...$this->compileIndexes($blueprint),
        ];
    }

    /**
     * @return list<string>
     */
    public function compileAlter(Blueprint $blueprint): array
    {
        $table = $this->grammar->wrap($blueprint->table);
        $statements = [];

        foreach ($blueprint->columns() as $column) {
            $statements[] = sprintf('alter table %s add column %s', $table, $this->columnDefinition($column, inlineReferences: true));
        }

        foreach ($blueprint->droppedColumns() as $column) {
            $statements[] = sprintf('alter table %s drop column %s', $table, $this->grammar->wrap($column));
        }

        return [...$statements, ...$this->compileIndexes($blueprint)];
    }

    public function compileDrop(string $table): string
    {
        return 'drop table ' . $this->grammar->wrap($table);
    }

    public function compileDropIfExists(string $table): string
    {
        return 'drop table if exists ' . $this->grammar->wrap($table);
    }

    public function compileRename(string $from, string $to): string
    {
        return sprintf('alter table %s rename to %s', $this->grammar->wrap($from), $this->grammar->wrap($to));
    }

    /**
     * @return list<string>
     */
    protected function compileIndexes(Blueprint $blueprint): array
    {
        $statements = [];

        foreach ($blueprint->indexes() as $index) {
            if ($index['type'] === 'primary') {
                continue;
            }

            $name = $blueprint->table . '_' . implode('_', $index['columns']) . '_' . $index['type'];
            $statements[] = sprintf(
                'create %sindex %s on %s (%s)',
                $index['type'] === 'unique' ? 'unique ' : '',
                $this->grammar->wrap($name),
                $this->grammar->wrap($blueprint->table),
                $this->columnize($index['columns']),
            );
        }

        return $statements;
    }

    protected function columnDefinition(ColumnDefinition $column, bool $inlineReferences = false): string
    {
        if ($column->type === 'id') {
            return $this->grammar->wrap($column->name) . ' ' . $this->autoIncrementPrimaryKey();
        }

        $sql = $this->grammar->wrap($column->name) . ' ' . $this->type($column) . $this->unsignedModifier($column);
        $sql .= $column->nullable ? ' null' : ' not null';

        if ($column->hasDefault) {
            $sql .= ' default ' . $this->defaultValue($column->default);
        }

        if ($column->primary) {
            $sql .= ' primary key';
        }

        if ($inlineReferences && $column->references !== null) {
            $sql .= ' ' . $this->referencesClause($column);
        }

        return $sql;
    }

    protected function unsignedModifier(ColumnDefinition $column): string
    {
        return '';
    }

    protected function referencesClause(ColumnDefinition $column): string
    {
        $references = $column->references;

        if ($references === null) {
            return '';
        }

        $sql = sprintf('references %s (%s)', $this->grammar->wrap($references['table']), $this->grammar->wrap($references['column']));

        if ($references['onDelete'] !== null) {
            $sql .= ' on delete ' . $references['onDelete'];
        }

        if ($references['onUpdate'] !== null) {
            $sql .= ' on update ' . $references['onUpdate'];
        }

        return $sql;
    }

    protected function defaultValue(mixed $value): string
    {
        return match (true) {
            $value instanceof Expression => $value->value,
            $value === null => 'null',
            is_bool($value) => $this->booleanLiteral($value),
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => "'" . str_replace("'", "''", $value) . "'",
            default => throw new InvalidArgumentException(sprintf('Valor padrão não suportado: %s.', get_debug_type($value))),
        };
    }

    protected function booleanLiteral(bool $value): string
    {
        return $value ? '1' : '0';
    }

    /**
     * @param list<string> $columns
     */
    protected function columnize(array $columns): string
    {
        return implode(', ', array_map($this->grammar->wrap(...), $columns));
    }

    protected function parameter(ColumnDefinition $column, string $key, int $default): int
    {
        return $column->parameters[$key] ?? $default;
    }
}
