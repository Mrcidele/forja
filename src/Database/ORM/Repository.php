<?php

declare(strict_types=1);

namespace Forja\Database\ORM;

/**
 * Base para repositórios de uma entidade específica.
 *
 * @template T of object
 */
abstract class Repository
{
    public function __construct(
        protected readonly EntityManager $manager,
    ) {
    }

    /**
     * @return class-string<T>
     */
    abstract protected function entity(): string;

    /**
     * @return T|null
     */
    public function find(int|string $id): ?object
    {
        return $this->manager->find($this->entity(), $id);
    }

    /**
     * @return list<T>
     */
    public function findAll(): array
    {
        return $this->manager->findAll($this->entity());
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     *
     * @return list<T>
     */
    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        return $this->manager->findBy($this->entity(), $criteria, $orderBy, $limit, $offset);
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return T|null
     */
    public function findOneBy(array $criteria): ?object
    {
        return $this->manager->findOneBy($this->entity(), $criteria);
    }

    /**
     * @return EntityQuery<T>
     */
    public function query(): EntityQuery
    {
        return $this->manager->query($this->entity());
    }

    /**
     * @param T $entity
     */
    public function save(object $entity): void
    {
        $this->manager->save($entity);
    }

    /**
     * @param T $entity
     */
    public function delete(object $entity): void
    {
        $this->manager->delete($entity);
    }
}
