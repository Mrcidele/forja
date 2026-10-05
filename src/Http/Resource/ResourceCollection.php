<?php

declare(strict_types=1);

namespace Forja\Http\Resource;

use Forja\Database\Query\Paginator;
use Forja\Http\ResponseFactory;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;

/**
 * Lista de recursos sob "data"; quando vem de um Paginator, inclui "meta".
 */
final readonly class ResourceCollection implements JsonSerializable
{
    /**
     * @param class-string<JsonResource> $resource
     * @param iterable<mixed>|Paginator<mixed> $items
     */
    public function __construct(
        private string $resource,
        private iterable|Paginator $items,
    ) {
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta?: array<string, int>}
     */
    public function jsonSerialize(): array
    {
        $items = $this->items instanceof Paginator ? $this->items->items : $this->items;
        $data = [];

        foreach ($items as $item) {
            $data[] = new ($this->resource)($item)->toArray();
        }

        if (! $this->items instanceof Paginator) {
            return ['data' => $data];
        }

        return ['data' => $data, 'meta' => $this->items->jsonSerialize()['meta']];
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function response(int $status = 200, array $headers = [], ResponseFactory $responses = new ResponseFactory()): ResponseInterface
    {
        return $responses->json($this, $status, $headers);
    }
}
