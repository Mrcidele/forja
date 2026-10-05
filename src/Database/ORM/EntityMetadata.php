<?php

declare(strict_types=1);

namespace Forja\Database\ORM;

/**
 * Mapeamento de uma entidade para sua tabela.
 *
 * @template T of object
 */
final readonly class EntityMetadata
{
    /**
     * @param class-string<T> $class
     * @param array<string, ColumnMetadata> $columns propriedade => coluna
     * @param array<string, RelationMetadata> $relations
     */
    public function __construct(
        public string $class,
        public string $table,
        public string $idProperty,
        public bool $generatedId,
        public array $columns,
        public array $relations,
    ) {
    }

    public function idColumn(): string
    {
        return $this->columns[$this->idProperty]->column;
    }

    public function column(string $property): string
    {
        return $this->columns[$property]->column
            ?? throw new MappingException(sprintf('A propriedade [%s::$%s] não está mapeada.', $this->class, $property));
    }

    public function propertyForColumn(string $column): ?string
    {
        foreach ($this->columns as $metadata) {
            if ($metadata->column === $column) {
                return $metadata->property;
            }
        }

        return null;
    }

    public function relation(string $property): RelationMetadata
    {
        return $this->relations[$property]
            ?? throw new MappingException(sprintf('A relação [%s::$%s] não existe.', $this->class, $property));
    }
}
