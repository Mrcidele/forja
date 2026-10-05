<?php

declare(strict_types=1);

namespace Forja\Routing;

use Forja\Routing\Exception\InvalidRouteException;

/**
 * Converte rotas em CompiledRoutes.
 *
 * As rotas dinâmicas de cada método viram poucas regexes no formato
 * ~^(?|/users/(\d+)(*:0)|/posts/([^/]+)(*:1))$~: um único preg_match testa
 * o bloco inteiro e o marcador (*:N) indica qual rota casou.
 *
 * @phpstan-import-type DynamicChunk from CompiledRoutes
 */
final readonly class RouteCompiler
{
    public function __construct(
        private int $chunkSize = 30,
    ) {
    }

    /**
     * @param iterable<Route> $routes
     */
    public function compile(iterable $routes): CompiledRoutes
    {
        $list = [];
        $static = [];
        /** @var array<string, list<array{int, string, list<string>, array<string, string>}>> $dynamicByMethod */
        $dynamicByMethod = [];
        $names = [];

        foreach ($routes as $route) {
            $index = count($list);
            $list[] = $route;

            if ($route->name !== null) {
                if (isset($names[$route->name])) {
                    throw new InvalidRouteException(sprintf('O nome de rota [%s] já está em uso.', $route->name));
                }

                $names[$route->name] = $index;
            }

            if (RoutePattern::isStatic($route->path)) {
                foreach ($route->methods as $method) {
                    if (isset($static[$method][$route->path])) {
                        throw new InvalidRouteException(sprintf('A rota %s %s já foi registrada.', $method, $route->path));
                    }

                    $static[$method][$route->path] = $index;
                }

                continue;
            }

            [$regex, $variables, $types] = $this->compilePath($route->path);

            foreach ($route->methods as $method) {
                $dynamicByMethod[$method][] = [$index, $regex, $variables, $types];
            }
        }

        $dynamic = [];

        foreach ($dynamicByMethod as $method => $entries) {
            foreach (array_chunk($entries, max(1, $this->chunkSize)) as $chunk) {
                $dynamic[$method][] = $this->combine($chunk);
            }
        }

        return new CompiledRoutes($list, $static, $dynamic, $names);
    }

    /**
     * @return array{string, list<string>, array<string, string>}
     */
    private function compilePath(string $path): array
    {
        $regex = '';
        $variables = [];
        $types = [];

        foreach (RoutePattern::parse($path) as $part) {
            if (is_string($part)) {
                $regex .= preg_quote($part, '~');

                continue;
            }

            $regex .= '(' . $part['regex'] . ')';
            $variables[] = $part['name'];

            if ($part['type'] !== null) {
                $types[$part['name']] = $part['type'];
            }
        }

        return [$regex, $variables, $types];
    }

    /**
     * @param list<array{int, string, list<string>, array<string, string>}> $chunk
     *
     * @return DynamicChunk
     */
    private function combine(array $chunk): array
    {
        $alternatives = [];
        $routes = [];

        foreach ($chunk as [$index, $regex, $variables, $types]) {
            $alternatives[] = $regex . '(*:' . $index . ')';
            $routes[$index] = [$index, $variables, $types];
        }

        return [
            'regex' => '~^(?|' . implode('|', $alternatives) . ')$~',
            'routes' => $routes,
        ];
    }
}
