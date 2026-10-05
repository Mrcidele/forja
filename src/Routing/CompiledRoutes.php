<?php

declare(strict_types=1);

namespace Forja\Routing;

/**
 * Mapa de rotas pronto para casamento: rotas estáticas indexadas por caminho e
 * dinâmicas agrupadas em regexes combinadas por método HTTP.
 *
 * É exportável com var_export(), o que permite gravá-lo em cache.
 *
 * @phpstan-type DynamicChunk array{regex: string, routes: array<int|string, array{int, list<string>, array<string, string>}>}
 */
final readonly class CompiledRoutes
{
    /**
     * @param list<Route> $routes
     * @param array<string, array<string, int>> $static método => caminho => índice da rota
     * @param array<string, list<DynamicChunk>> $dynamic método => blocos de regex combinada
     * @param array<string, int> $names nome => índice da rota
     */
    public function __construct(
        public array $routes,
        public array $static,
        public array $dynamic,
        public array $names,
    ) {
    }

    /**
     * @param array{routes: list<Route>, static: array<string, array<string, int>>, dynamic: array<string, list<DynamicChunk>>, names: array<string, int>} $state
     */
    public static function __set_state(array $state): self
    {
        return new self($state['routes'], $state['static'], $state['dynamic'], $state['names']);
    }

    public function route(string $name): ?Route
    {
        return isset($this->names[$name]) ? $this->routes[$this->names[$name]] : null;
    }
}
