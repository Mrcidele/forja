<?php

declare(strict_types=1);

namespace Forja\Routing;

use Closure;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Coleção mutável de rotas, com suporte a grupos (prefixo de caminho e de nome).
 *
 * @phpstan-import-type Handler from Route
 *
 * @implements IteratorAggregate<int, Route>
 */
final class RouteCollection implements Countable, IteratorAggregate
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var list<array{prefix: string, name: string}> */
    private array $groups = [];

    private int $version = 0;

    /**
     * @param list<string>|string $methods
     * @param Handler $handler
     */
    public function add(array|string $methods, string $path, Closure|string|array $handler, ?string $name = null): Route
    {
        $prefix = '';
        $namePrefix = '';

        foreach ($this->groups as $group) {
            $prefix .= '/' . trim($group['prefix'], '/');
            $namePrefix .= $group['name'];
        }

        $route = new Route(
            is_string($methods) ? [$methods] : $methods,
            $prefix . '/' . ltrim($path, '/'),
            $handler,
            $name !== null ? $namePrefix . $name : null,
        );

        $this->routes[] = $route;
        $this->version++;

        return $route;
    }

    /**
     * @param Handler $handler
     */
    public function get(string $path, Closure|string|array $handler, ?string $name = null): Route
    {
        return $this->add('GET', $path, $handler, $name);
    }

    /**
     * @param Handler $handler
     */
    public function post(string $path, Closure|string|array $handler, ?string $name = null): Route
    {
        return $this->add('POST', $path, $handler, $name);
    }

    /**
     * @param Handler $handler
     */
    public function put(string $path, Closure|string|array $handler, ?string $name = null): Route
    {
        return $this->add('PUT', $path, $handler, $name);
    }

    /**
     * @param Handler $handler
     */
    public function patch(string $path, Closure|string|array $handler, ?string $name = null): Route
    {
        return $this->add('PATCH', $path, $handler, $name);
    }

    /**
     * @param Handler $handler
     */
    public function delete(string $path, Closure|string|array $handler, ?string $name = null): Route
    {
        return $this->add('DELETE', $path, $handler, $name);
    }

    /**
     * @param Handler $handler
     */
    public function options(string $path, Closure|string|array $handler, ?string $name = null): Route
    {
        return $this->add('OPTIONS', $path, $handler, $name);
    }

    /**
     * @param Handler $handler
     */
    public function any(string $path, Closure|string|array $handler, ?string $name = null): Route
    {
        return $this->add(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $path, $handler, $name);
    }

    /**
     * Registra as rotas definidas em $callback com prefixo de caminho e de nome.
     *
     * @param Closure(self): void $callback
     */
    public function group(string $prefix, Closure $callback, string $name = ''): void
    {
        $this->groups[] = ['prefix' => $prefix, 'name' => $name];

        try {
            $callback($this);
        } finally {
            array_pop($this->groups);
        }
    }

    /**
     * @return list<Route>
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * Incrementado a cada rota adicionada; permite invalidar compilações anteriores.
     */
    public function version(): int
    {
        return $this->version;
    }

    public function count(): int
    {
        return count($this->routes);
    }

    public function getIterator(): Traversable
    {
        yield from $this->routes;
    }
}
