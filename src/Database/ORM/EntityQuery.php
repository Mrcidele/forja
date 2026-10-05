<?php

declare(strict_types=1);

namespace Forja\Database\ORM;

use Closure;
use Forja\Database\Expression;
use Forja\Database\Query\Builder;
use Forja\Database\Query\Paginator;

/**
 * Consulta que devolve entidades em vez de arrays. Condições usam nomes de coluna.
 *
 * @template T of object
 */
final class EntityQuery
{
    /** @var list<string> */
    private array $with = [];

    /**
     * @param EntityMetadata<T> $metadata
     */
    public function __construct(
        private readonly EntityManager $manager,
        private readonly EntityMetadata $metadata,
        private readonly Builder $builder,
    ) {
    }

    /**
     * @param string|Expression|(Closure(Builder): mixed)|array<string, mixed> $column
     */
    public function where(string|Expression|Closure|array $column, mixed $operator = null, mixed $value = null): static
    {
        func_num_args() === 2 ? $this->builder->where($column, $operator) : $this->builder->where($column, $operator, $value);

        return $this;
    }

    /**
     * @param string|Expression|(Closure(Builder): mixed)|array<string, mixed> $column
     */
    public function orWhere(string|Expression|Closure|array $column, mixed $operator = null, mixed $value = null): static
    {
        func_num_args() === 2 ? $this->builder->orWhere($column, $operator) : $this->builder->orWhere($column, $operator, $value);

        return $this;
    }

    /**
     * @param iterable<mixed> $values
     */
    public function whereIn(string $column, iterable $values): static
    {
        $this->builder->whereIn($column, $values);

        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->builder->whereNull($column);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->builder->orderBy($column, $direction);

        return $this;
    }

    public function limit(?int $limit): static
    {
        $this->builder->limit($limit);

        return $this;
    }

    public function offset(?int $offset): static
    {
        $this->builder->offset($offset);

        return $this;
    }

    /**
     * Relações a carregar junto (eager loading), pelo nome da propriedade.
     */
    public function with(string ...$relations): static
    {
        $this->with = [...$this->with, ...array_values($relations)];

        return $this;
    }

    /**
     * @return list<T>
     */
    public function get(): array
    {
        return $this->hydrate($this->builder->get());
    }

    /**
     * @return T|null
     */
    public function first(): ?object
    {
        return $this->hydrate(array_filter([(clone $this->builder)->first()]))[0] ?? null;
    }

    public function count(): int
    {
        return (clone $this->builder)->count();
    }

    public function exists(): bool
    {
        return (clone $this->builder)->exists();
    }

    /**
     * @return Paginator<T>
     */
    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $page = (clone $this->builder)->paginate($perPage, $page);

        return new Paginator($this->hydrate($page->items), $page->total, $page->perPage, $page->currentPage);
    }

    /**
     * Acesso ao query builder para casos não cobertos aqui.
     */
    public function builder(): Builder
    {
        return $this->builder;
    }

    /**
     * @param array<array-key, array<string, mixed>> $rows
     *
     * @return list<T>
     */
    private function hydrate(array $rows): array
    {
        $entities = array_values(array_map(fn (array $row): object => $this->manager->hydrator()->hydrate($this->metadata, $row), $rows));

        if ($entities !== [] && $this->with !== []) {
            $this->manager->load($entities, ...$this->with);
        }

        return $entities;
    }
}
