<?php

declare(strict_types=1);

namespace Forja\Database\Query;

use Closure;
use JsonSerializable;

/**
 * Página de resultados com metadados de paginação.
 *
 * @template T
 */
final readonly class Paginator implements JsonSerializable
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $currentPage,
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    /**
     * @template U
     *
     * @param Closure(T): U $callback
     *
     * @return self<U>
     */
    public function map(Closure $callback): self
    {
        return new self(array_map($callback, $this->items), $this->total, $this->perPage, $this->currentPage);
    }

    /**
     * @return array{data: list<T>, meta: array{total: int, per_page: int, current_page: int, last_page: int}}
     */
    public function jsonSerialize(): array
    {
        return [
            'data' => $this->items,
            'meta' => [
                'total' => $this->total,
                'per_page' => $this->perPage,
                'current_page' => $this->currentPage,
                'last_page' => $this->lastPage(),
            ],
        ];
    }
}
