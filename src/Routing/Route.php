<?php

declare(strict_types=1);

namespace Forja\Routing;

use Closure;
use Forja\Routing\Exception\InvalidRouteException;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Definição imutável de uma rota.
 *
 * @phpstan-type Handler Closure|class-string|array{class-string, string}
 */
final readonly class Route
{
    /** @var list<string> */
    public array $methods;

    public string $path;

    /**
     * @param list<string> $methods
     * @param Handler $handler
     * @param list<class-string<MiddlewareInterface>> $middleware executados antes do handler, na ordem
     */
    public function __construct(
        array $methods,
        string $path,
        public Closure|string|array $handler,
        public ?string $name = null,
        public array $middleware = [],
    ) {
        if ($methods === []) {
            throw new InvalidRouteException(sprintf('A rota [%s] precisa de ao menos um método HTTP.', $path));
        }

        $this->methods = array_values(array_unique(array_map(strtoupper(...), $methods)));
        $this->path = self::normalizePath($path);
    }

    public static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return preg_replace('#/{2,}#', '/', $path) ?? $path;
    }

    /**
     * Rotas com closures não podem ser exportadas para o cache.
     */
    public function isCacheable(): bool
    {
        return ! $this->handler instanceof Closure;
    }

    /**
     * Descrição legível do handler, usada em listagens e mensagens.
     */
    public function handlerName(): string
    {
        return match (true) {
            $this->handler instanceof Closure => 'Closure',
            is_string($this->handler) => $this->handler,
            default => $this->handler[0] . '@' . $this->handler[1],
        };
    }

    /**
     * Permite que var_export() reconstrua a rota a partir do cache.
     *
     * @param array{methods: list<string>, path: string, handler: Handler, name: string|null, middleware: list<class-string<MiddlewareInterface>>} $state
     */
    public static function __set_state(array $state): self
    {
        return new self($state['methods'], $state['path'], $state['handler'], $state['name'], $state['middleware']);
    }
}
