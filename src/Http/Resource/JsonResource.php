<?php

declare(strict_types=1);

namespace Forja\Http\Resource;

use Forja\Database\Query\Paginator;
use Forja\Http\ResponseFactory;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;

/**
 * Transforma um objeto (entidade, DTO, array) na representação JSON da API.
 *
 * Por padrão a saída fica sob a chave "data"; redefina $wrap como null para
 * devolver o array sem envelope.
 */
abstract class JsonResource implements JsonSerializable
{
    protected ?string $wrap = 'data';

    final public function __construct(
        public readonly mixed $resource,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Dados extras no nível superior da resposta (ex.: links).
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [];
    }

    public static function make(mixed $resource): static
    {
        return new static($resource);
    }

    /**
     * @param iterable<mixed>|Paginator<mixed> $resources
     */
    public static function collection(iterable|Paginator $resources): ResourceCollection
    {
        return new ResourceCollection(static::class, $resources);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->wrap === null ? $this->toArray() : [$this->wrap => $this->toArray(), ...$this->with()];
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function response(int $status = 200, array $headers = [], ResponseFactory $responses = new ResponseFactory()): ResponseInterface
    {
        return $responses->json($this, $status, $headers);
    }
}
