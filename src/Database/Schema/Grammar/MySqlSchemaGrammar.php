<?php

declare(strict_types=1);

namespace Forja\Database\Schema\Grammar;

use Forja\Database\Schema\Blueprint;
use Forja\Database\Schema\ColumnDefinition;

final class MySqlSchemaGrammar extends SchemaGrammar
{
    public function compileHasTable(string $table): array
    {
        return ['select count(*) from information_schema.tables where table_schema = database() and table_name = ?', [$table]];
    }

    public function compileAlter(Blueprint $blueprint): array
    {
        $statements = parent::compileAlter($blueprint);
        $table = $this->grammar->wrap($blueprint->table);

        foreach ($blueprint->columns() as $column) {
            if ($column->references !== null) {
                $statements[] = sprintf(
                    'alter table %s add constraint %s foreign key (%s) %s',
                    $table,
                    $this->grammar->wrap($blueprint->table . '_' . $column->name . '_foreign'),
                    $this->grammar->wrap($column->name),
                    $this->referencesClause($column),
                );
            }
        }

        return $statements;
    }

    public function compileRename(string $from, string $to): string
    {
        return sprintf('rename table %s to %s', $this->grammar->wrap($from), $this->grammar->wrap($to));
    }

    protected function columnDefinition(ColumnDefinition $column, bool $inlineReferences = false): string
    {
        // O MySQL ignora REFERENCES inline; as chaves vão como constraints separadas.
        return parent::columnDefinition($column, false);
    }

    protected function autoIncrementPrimaryKey(): string
    {
        return 'bigint unsigned not null auto_increment primary key';
    }

    protected function unsignedModifier(ColumnDefinition $column): string
    {
        return $column->unsigned ? ' unsigned' : '';
    }

    protected function type(ColumnDefinition $column): string
    {
        return match ($column->type) {
            'string' => 'varchar(' . $this->parameter($column, 'length', 255) . ')',
            'text' => 'text',
            'integer' => 'int',
            'bigInteger' => 'bigint',
            'boolean' => 'tinyint(1)',
            'decimal' => sprintf('decimal(%d, %d)', $this->parameter($column, 'precision', 8), $this->parameter($column, 'scale', 2)),
            'float' => 'double',
            'date' => 'date',
            'dateTime' => 'datetime',
            'timestamp' => 'timestamp',
            'json' => 'json',
            'uuid' => 'char(36)',
            default => $column->type,
        };
    }
}
