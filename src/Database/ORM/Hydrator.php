<?php

declare(strict_types=1);

namespace Forja\Database\ORM;

use BackedEnum;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use ReflectionClass;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Converte linhas do banco em entidades e entidades em linhas, respeitando
 * os tipos declarados nas propriedades (int, float, bool, DateTimeImmutable,
 * BackedEnum e array/JSON). Funciona com propriedades privadas e readonly.
 */
final class Hydrator
{
    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param array<string, mixed> $row
     *
     * @return T
     */
    public function hydrate(EntityMetadata $metadata, array $row): object
    {
        $entity = new ReflectionClass($metadata->class)->newInstanceWithoutConstructor();

        foreach ($metadata->columns as $column) {
            if (array_key_exists($column->column, $row)) {
                $this->set($entity, $column->property, $this->toPhp($row[$column->column], $column));
            }
        }

        return $entity;
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @return array<string, mixed> coluna => valor; propriedades não inicializadas ficam de fora
     */
    public function extract(EntityMetadata $metadata, object $entity): array
    {
        $row = [];

        foreach ($metadata->columns as $column) {
            $property = new ReflectionProperty($entity, $column->property);

            if ($property->isInitialized($entity)) {
                $row[$column->column] = $this->toDatabase($property->getValue($entity));
            }
        }

        return $row;
    }

    public function get(object $entity, string $property): mixed
    {
        $reflection = new ReflectionProperty($entity, $property);

        return $reflection->isInitialized($entity) ? $reflection->getValue($entity) : null;
    }

    /**
     * Atribui o valor no escopo da classe que declara a propriedade, o que
     * permite inicializar propriedades readonly e privadas.
     */
    public function set(object $entity, string $property, mixed $value): void
    {
        $scope = new ReflectionProperty($entity, $property)->getDeclaringClass()->getName();

        Closure::bind(static function (object $entity) use ($property, $value): void {
            $entity->{$property} = $value;
        }, null, $scope)($entity);
    }

    public function isReadonlyInitialized(object $entity, string $property): bool
    {
        $reflection = new ReflectionProperty($entity, $property);

        return $reflection->isReadOnly() && $reflection->isInitialized($entity);
    }

    private function toPhp(mixed $value, ColumnMetadata $column): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $column->type;

        return match (true) {
            $type === 'int' => is_numeric($value) ? (int) $value : $value,
            $type === 'float' => is_numeric($value) ? (float) $value : $value,
            $type === 'bool' => (bool) $value,
            $type === 'string' => is_scalar($value) ? (string) $value : $value,
            $type === 'array' => is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value,
            $type === DateTimeImmutable::class, $type === DateTimeInterface::class => is_string($value) ? new DateTimeImmutable($value) : $value,
            $type !== null && is_subclass_of($type, BackedEnum::class) => $this->toEnum($type, $value),
            default => $value,
        };
    }

    /**
     * @param class-string<BackedEnum> $enum
     */
    private function toEnum(string $enum, mixed $value): BackedEnum
    {
        $backing = new ReflectionEnum($enum)->getBackingType();
        $isInt = $backing instanceof ReflectionNamedType && $backing->getName() === 'int';

        return $enum::from($isInt ? (int) (is_numeric($value) ? $value : 0) : (is_scalar($value) ? (string) $value : ''));
    }

    private function toDatabase(mixed $value): mixed
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof BackedEnum => $value->value,
            is_array($value) => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            default => $value,
        };
    }
}
