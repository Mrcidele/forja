<?php

declare(strict_types=1);

namespace Forja\Database\ORM;

use Closure;
use Forja\Database\Connection;

/**
 * Data Mapper: as entidades são objetos PHP comuns, e este serviço os busca,
 * persiste e remove a partir do mapeamento declarado em atributos.
 */
final readonly class EntityManager
{
    public function __construct(
        private Connection $connection,
        private MetadataFactory $metadata = new MetadataFactory(),
        private Hydrator $hydrator = new Hydrator(),
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public function find(string $class, int|string $id): ?object
    {
        $metadata = $this->metadata($class);

        return $this->query($class)->where($metadata->idColumn(), '=', $id)->first();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function findAll(string $class): array
    {
        return $this->query($class)->get();
    }

    /**
     * Busca por igualdade em propriedades: findBy(User::class, ['active' => true], ['name' => 'asc']).
     *
     * @template T of object
     *
     * @param class-string<T> $class
     * @param array<string, mixed> $criteria propriedade => valor
     * @param array<string, string> $orderBy propriedade => asc|desc
     *
     * @return list<T>
     */
    public function findBy(string $class, array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $metadata = $this->metadata($class);
        $query = $this->query($class)->limit($limit)->offset($offset);

        foreach ($criteria as $property => $value) {
            is_iterable($value)
                ? $query->whereIn($metadata->column($property), $value)
                : $query->where($metadata->column($property), '=', $value);
        }

        foreach ($orderBy as $property => $direction) {
            $query->orderBy($metadata->column($property), $direction);
        }

        return $query->get();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @param array<string, mixed> $criteria
     *
     * @return T|null
     */
    public function findOneBy(string $class, array $criteria): ?object
    {
        return $this->findBy($class, $criteria, [], 1)[0] ?? null;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return EntityQuery<T>
     */
    public function query(string $class): EntityQuery
    {
        $metadata = $this->metadata($class);

        return new EntityQuery($this, $metadata, $this->connection->table($metadata->table));
    }

    /**
     * Insere a entidade (preenchendo o ID gerado) ou atualiza se já existir.
     */
    public function save(object $entity): void
    {
        $metadata = $this->metadata($entity::class);
        $row = $this->hydrator->extract($metadata, $entity);
        $id = $this->hydrator->get($entity, $metadata->idProperty);
        $table = $this->connection->table($metadata->table);

        if ($id !== null && ($metadata->generatedId || $table->where($metadata->idColumn(), $id)->exists())) {
            unset($row[$metadata->idColumn()]);

            if ($row !== []) {
                $this->connection->table($metadata->table)->where($metadata->idColumn(), $id)->update($row);
            }

            return;
        }

        if ($metadata->generatedId) {
            unset($row[$metadata->idColumn()]);
            $newId = $this->connection->table($metadata->table)->insertGetId($row, $metadata->idColumn());
            $this->hydrator->set($entity, $metadata->idProperty, $this->castId($metadata, $newId));

            return;
        }

        $this->connection->table($metadata->table)->insert($row);
    }

    public function delete(object $entity): void
    {
        $metadata = $this->metadata($entity::class);
        $id = $this->hydrator->get($entity, $metadata->idProperty);

        if ($id === null) {
            throw new MappingException(sprintf('Não é possível remover uma entidade [%s] sem ID.', $entity::class));
        }

        $this->connection->table($metadata->table)->where($metadata->idColumn(), $id)->delete();
    }

    /**
     * Carrega relações (#[HasMany], #[BelongsTo]) de uma ou mais entidades
     * do mesmo tipo com uma consulta por relação.
     *
     * @param object|list<object> $entities
     */
    public function load(object|array $entities, string ...$relations): void
    {
        $entities = is_array($entities) ? $entities : [$entities];

        if ($entities === []) {
            return;
        }

        $metadata = $this->metadata($entities[0]::class);

        foreach ($relations as $name) {
            $relation = $metadata->relation($name);

            $relation->type === 'hasMany'
                ? $this->loadHasMany($metadata, $relation, $entities)
                : $this->loadBelongsTo($metadata, $relation, $entities);
        }
    }

    /**
     * @template T
     *
     * @param Closure(self): T $callback
     *
     * @return T
     */
    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction(fn (): mixed => $callback($this));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return EntityMetadata<T>
     */
    public function metadata(string $class): EntityMetadata
    {
        return $this->metadata->for($class);
    }

    public function hydrator(): Hydrator
    {
        return $this->hydrator;
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * @param EntityMetadata<object> $metadata
     * @param list<object> $entities
     */
    private function loadHasMany(EntityMetadata $metadata, RelationMetadata $relation, array $entities): void
    {
        $localProperty = $metadata->propertyForColumn($relation->localKey)
            ?? throw new MappingException(sprintf('A coluna [%s] de [%s] precisa estar mapeada para a relação [%s].', $relation->localKey, $metadata->class, $relation->property));
        $keys = array_values(array_unique(array_filter(array_map(fn (object $entity): mixed => $this->hydrator->get($entity, $localProperty), $entities), static fn (mixed $key): bool => $key !== null), SORT_REGULAR));

        $targetMetadata = $this->metadata($relation->target);
        $grouped = [];

        if ($keys !== []) {
            foreach ($this->connection->table($targetMetadata->table)->whereIn($relation->foreignKey, $keys)->get() as $row) {
                $grouped[$this->key($row[$relation->foreignKey] ?? null)][] = $this->hydrator->hydrate($targetMetadata, $row);
            }
        }

        foreach ($entities as $entity) {
            $this->hydrator->set($entity, $relation->property, $grouped[$this->key($this->hydrator->get($entity, $localProperty))] ?? []);
        }
    }

    /**
     * @param EntityMetadata<object> $metadata
     * @param list<object> $entities
     */
    private function loadBelongsTo(EntityMetadata $metadata, RelationMetadata $relation, array $entities): void
    {
        $foreignProperty = $metadata->propertyForColumn($relation->foreignKey)
            ?? throw new MappingException(sprintf('A coluna [%s] de [%s] precisa estar mapeada com #[Column] para a relação [%s].', $relation->foreignKey, $metadata->class, $relation->property));
        $keys = array_values(array_unique(array_filter(array_map(fn (object $entity): mixed => $this->hydrator->get($entity, $foreignProperty), $entities), static fn (mixed $key): bool => $key !== null), SORT_REGULAR));

        $targetMetadata = $this->metadata($relation->target);
        $owners = [];

        if ($keys !== []) {
            foreach ($this->connection->table($targetMetadata->table)->whereIn($relation->localKey, $keys)->get() as $row) {
                $owners[$this->key($row[$relation->localKey] ?? null)] = $this->hydrator->hydrate($targetMetadata, $row);
            }
        }

        foreach ($entities as $entity) {
            $this->hydrator->set($entity, $relation->property, $owners[$this->key($this->hydrator->get($entity, $foreignProperty))] ?? null);
        }
    }

    /**
     * Normaliza chaves vindas do banco (int ou string numérica) para agrupar.
     */
    private function key(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param EntityMetadata<object> $metadata
     */
    private function castId(EntityMetadata $metadata, int|string $id): int|string
    {
        return ($metadata->columns[$metadata->idProperty]->type ?? null) === 'string' ? (string) $id : $id;
    }
}
