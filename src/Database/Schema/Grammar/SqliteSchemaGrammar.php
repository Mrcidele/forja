<?php

declare(strict_types=1);

namespace Forja\Database\Schema\Grammar;

use Forja\Database\Schema\ColumnDefinition;

final class SqliteSchemaGrammar extends SchemaGrammar
{
    public function compileHasTable(string $table): array
    {
        return ["select count(*) from sqlite_master where type = 'table' and name = ?", [$table]];
    }

    protected function autoIncrementPrimaryKey(): string
    {
        return 'integer primary key autoincrement not null';
    }

    protected function type(ColumnDefinition $column): string
    {
        return match ($column->type) {
            'string' => 'varchar(' . $this->parameter($column, 'length', 255) . ')',
            'text', 'json' => 'text',
            'integer', 'bigInteger', 'boolean' => 'integer',
            'decimal' => sprintf('numeric(%d, %d)', $this->parameter($column, 'precision', 8), $this->parameter($column, 'scale', 2)),
            'float' => 'real',
            'date' => 'date',
            'dateTime', 'timestamp' => 'datetime',
            'uuid' => 'varchar(36)',
            default => $column->type,
        };
    }
}
