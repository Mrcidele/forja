<?php

declare(strict_types=1);

namespace Forja\Routing;

use BackedEnum;
use Forja\Routing\Exception\RouteNotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use Stringable;

/**
 * Casa requisições com as rotas e gera URLs a partir de rotas nomeadas.
 *
 * O mapa é compilado sob demanda a partir da RouteCollection, ou carregado
 * pronto do cache em produção (Router::fromCompiled()).
 */
final class Router
{
    private ?CompiledRoutes $compiled = null;

    private int $compiledVersion = -1;

    public function __construct(
        private readonly RouteCollection $routes = new RouteCollection(),
        private readonly RouteCompiler $compiler = new RouteCompiler(),
    ) {
    }

    public static function fromCompiled(CompiledRoutes $compiled): self
    {
        $router = new self();
        $router->compiled = $compiled;
        $router->compiledVersion = $router->routes->version();

        return $router;
    }

    public function routes(): RouteCollection
    {
        return $this->routes;
    }

    public function compiled(): CompiledRoutes
    {
        if (!$this->compiled instanceof \Forja\Routing\CompiledRoutes || $this->compiledVersion !== $this->routes->version()) {
            $this->compiled = $this->compiler->compile($this->routes);
            $this->compiledVersion = $this->routes->version();
        }

        return $this->compiled;
    }

    public function match(ServerRequestInterface $request): RouteResult
    {
        return $this->matchPath($request->getMethod(), $request->getUri()->getPath());
    }

    public function matchPath(string $method, string $path): RouteResult
    {
        $compiled = $this->compiled();
        $method = strtoupper($method);
        $path = Route::normalizePath(rawurldecode($path));

        foreach ($method === 'HEAD' ? ['HEAD', 'GET'] : [$method] as $candidate) {
            $result = $this->matchMethod($compiled, $candidate, $path);

            if ($result instanceof \Forja\Routing\RouteResult) {
                return $result;
            }
        }

        $allowed = [];

        foreach (array_unique([...array_keys($compiled->static), ...array_keys($compiled->dynamic)]) as $other) {
            if ($other !== $method && $this->matchMethod($compiled, $other, $path) instanceof \Forja\Routing\RouteResult) {
                $allowed[] = $other;
            }
        }

        if ($allowed === []) {
            return RouteResult::notFound();
        }

        sort($allowed);

        return RouteResult::methodNotAllowed($allowed);
    }

    /**
     * Gera o caminho de uma rota nomeada. Parâmetros que não fazem parte do
     * caminho viram query string.
     *
     * @param array<string, scalar|BackedEnum|Stringable> $parameters
     */
    public function url(string $name, array $parameters = []): string
    {
        $route = $this->compiled()->route($name)
            ?? throw new RouteNotFoundException(sprintf('Rota [%s] não encontrada.', $name));

        $path = '';

        foreach (RoutePattern::parse($route->path) as $part) {
            if (is_string($part)) {
                $path .= $part;

                continue;
            }

            $path .= $this->segment($name, $part, $parameters);
            unset($parameters[$part['name']]);
        }

        $query = http_build_query(array_map($this->stringify(...), $parameters), '', '&', PHP_QUERY_RFC3986);

        return $query === '' ? $path : $path . '?' . $query;
    }

    private function matchMethod(CompiledRoutes $compiled, string $method, string $path): ?RouteResult
    {
        if (isset($compiled->static[$method][$path])) {
            return RouteResult::found($compiled->routes[$compiled->static[$method][$path]], []);
        }

        foreach ($compiled->dynamic[$method] ?? [] as $chunk) {
            if (preg_match($chunk['regex'], $path, $matches) !== 1 || ! isset($matches['MARK'])) {
                continue;
            }

            [$index, $variables, $types] = $chunk['routes'][(int) $matches['MARK']];
            $parameters = [];

            foreach ($variables as $position => $variable) {
                $value = $matches[$position + 1] ?? '';
                $parameters[$variable] = ($types[$variable] ?? null) === 'int' ? (int) $value : $value;
            }

            return RouteResult::found($compiled->routes[$index], $parameters);
        }

        return null;
    }

    /**
     * @param array{name: string, regex: string, type: string|null} $part
     * @param array<string, scalar|BackedEnum|Stringable> $parameters
     */
    private function segment(string $route, array $part, array $parameters): string
    {
        if (! array_key_exists($part['name'], $parameters)) {
            throw new RouteNotFoundException(sprintf('Parâmetro [%s] obrigatório para gerar a rota [%s].', $part['name'], $route));
        }

        $value = $this->stringify($parameters[$part['name']]);

        if (preg_match('~^(?:' . $part['regex'] . ')$~', $value) !== 1) {
            throw new RouteNotFoundException(sprintf('O valor [%s] não é válido para o parâmetro [%s] da rota [%s].', $value, $part['name'], $route));
        }

        return implode('/', array_map(rawurlencode(...), explode('/', $value)));
    }

    /**
     * @param scalar|BackedEnum|Stringable $value
     */
    private function stringify(mixed $value): string
    {
        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
