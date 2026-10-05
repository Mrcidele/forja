<?php

declare(strict_types=1);

namespace Forja\Database\ORM;

use Forja\Database\ORM\Attribute\BelongsTo;
use Forja\Database\ORM\Attribute\Column;
use Forja\Database\ORM\Attribute\HasMany;
use Forja\Database\ORM\Attribute\Id;
use Forja\Database\ORM\Attribute\Table;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Lê os atributos #[Table], #[Id], #[Column], #[HasMany] e #[BelongsTo]
 * e guarda o resultado por classe.
 */
final class MetadataFactory
{
    /** @var array<class-string, EntityMetadata<object>> */
    private array $metadata = [];

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return EntityMetadata<T>
     */
    public function for(string $class): EntityMetadata
    {
        /** @var EntityMetadata<T> $metadata */
        $metadata = $this->metadata[$class] ??= $this->build($class);

        return $metadata;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return EntityMetadata<T>
     */
    private function build(string $class): EntityMetadata
    {
        $reflection = new ReflectionClass($class);
        $table = ($reflection->getAttributes(Table::class)[0] ?? null)?->newInstance()
            ?? throw new MappingException(sprintf('A classe [%s] precisa do atributo #[Table].', $class));

        $columns = [];
        $relations = [];
        $idProperty = null;
        $generated = true;

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $id = ($property->getAttributes(Id::class)[0] ?? null)?->newInstance();
            $column = ($property->getAttributes(Column::class)[0] ?? null)?->newInstance();

            if ($id !== null) {
                $idProperty = $property->getName();
                $generated = $id->generated;
                $columns[$property->getName()] = $this->column($property, $id->column);
            } elseif ($column !== null) {
                $columns[$property->getName()] = $this->column($property, $column->name);
            }

            foreach ($property->getAttributes(HasMany::class) as $attribute) {
                $relation = $attribute->newInstance();
                $relations[$property->getName()] = new RelationMetadata($property->getName(), 'hasMany', $relation->target, $relation->foreignKey, $relation->localKey);
            }

            foreach ($property->getAttributes(BelongsTo::class) as $attribute) {
                $relation = $attribute->newInstance();
                $relations[$property->getName()] = new RelationMetadata($property->getName(), 'belongsTo', $relation->target, $relation->foreignKey, $relation->ownerKey);
            }
        }

        if ($idProperty === null) {
            throw new MappingException(sprintf('A classe [%s] precisa de uma propriedade com #[Id].', $class));
        }

        return new EntityMetadata($class, $table->name, $idProperty, $generated, $columns, $relations);
    }

    private function column(ReflectionProperty $property, ?string $name): ColumnMetadata
    {
        $type = $property->getType();

        return new ColumnMetadata(
            $property->getName(),
            $name ?? $this->snake($property->getName()),
            $type instanceof ReflectionNamedType ? $type->getName() : null,
            $type === null || $type->allowsNull(),
        );
    }

    private function snake(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
}
