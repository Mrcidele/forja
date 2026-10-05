<?php

declare(strict_types=1);

namespace Forja\Database\Schema;

/**
 * Descrição das colunas e índices de uma tabela a criar ou alterar.
 */
final class Blueprint
{
    /** @var list<ColumnDefinition> */
    private array $columns = [];

    /** @var list<array{type: 'unique'|'index'|'primary', columns: list<string>}> */
    private array $indexes = [];

    /** @var list<string> */
    private array $droppedColumns = [];

    public function __construct(
        public readonly string $table,
    ) {
    }

    /**
     * Chave primária inteira com autoincremento.
     */
    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->column($name, 'id');
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->column($name, 'string', ['length' => $length]);
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->column($name, 'text');
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->column($name, 'integer');
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->column($name, 'bigInteger');
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->column($name, 'boolean');
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->column($name, 'decimal', ['precision' => $precision, 'scale' => $scale]);
    }

    public function float(string $name): ColumnDefinition
    {
        return $this->column($name, 'float');
    }

    public function date(string $name): ColumnDefinition
    {
        return $this->column($name, 'date');
    }

    public function dateTime(string $name): ColumnDefinition
    {
        return $this->column($name, 'dateTime');
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->column($name, 'timestamp');
    }

    public function json(string $name): ColumnDefinition
    {
        return $this->column($name, 'json');
    }

    public function uuid(string $name): ColumnDefinition
    {
        return $this->column($name, 'uuid');
    }

    /**
     * Coluna para chave estrangeira; encadeie constrained('tabela').
     */
    public function foreignId(string $name): ColumnDefinition
    {
        return $this->column($name, 'bigInteger')->unsigned();
    }

    /**
     * Colunas created_at e updated_at, ambas opcionais.
     */
    public function timestamps(): void
    {
        $this->dateTime('created_at')->nullable();
        $this->dateTime('updated_at')->nullable();
    }

    public function unique(string ...$columns): void
    {
        $this->indexes[] = ['type' => 'unique', 'columns' => array_values($columns)];
    }

    public function index(string ...$columns): void
    {
        $this->indexes[] = ['type' => 'index', 'columns' => array_values($columns)];
    }

    public function primary(string ...$columns): void
    {
        $this->indexes[] = ['type' => 'primary', 'columns' => array_values($columns)];
    }

    public function dropColumn(string ...$columns): void
    {
        $this->droppedColumns = [...$this->droppedColumns, ...array_values($columns)];
    }

    /**
     * @return list<ColumnDefinition>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * Índices declarados, incluindo os criados por ->unique() nas colunas.
     *
     * @return list<array{type: 'unique'|'index'|'primary', columns: list<string>}>
     */
    public function indexes(): array
    {
        $fromColumns = [];

        foreach ($this->columns as $column) {
            if ($column->unique) {
                $fromColumns[] = ['type' => 'unique', 'columns' => [$column->name]];
            }
        }

        return [...$this->indexes, ...$fromColumns];
    }

    /**
     * @return list<string>
     */
    public function droppedColumns(): array
    {
        return $this->droppedColumns;
    }

    /**
     * @param array<string, int> $parameters
     */
    private function column(string $name, string $type, array $parameters = []): ColumnDefinition
    {
        $column = new ColumnDefinition($name, $type, $parameters);
        $this->columns[] = $column;

        return $column;
    }
}
