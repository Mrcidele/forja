<?php

declare(strict_types=1);

namespace Forja\Routing;

/**
 * Resultado do casamento de uma requisição com as rotas.
 */
final readonly class RouteResult
{
    /**
     * @param array<string, string|int> $parameters
     * @param list<string> $allowedMethods
     */
    private function __construct(
        public RouteStatus $status,
        public ?Route $route = null,
        public array $parameters = [],
        public array $allowedMethods = [],
    ) {
    }

    /**
     * @param array<string, string|int> $parameters
     */
    public static function found(Route $route, array $parameters): self
    {
        return new self(RouteStatus::Found, $route, $parameters);
    }

    public static function notFound(): self
    {
        return new self(RouteStatus::NotFound);
    }

    /**
     * @param list<string> $allowedMethods
     */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        return new self(RouteStatus::MethodNotAllowed, allowedMethods: $allowedMethods);
    }

    /**
     * @phpstan-assert-if-true !null $this->route
     */
    public function isFound(): bool
    {
        return $this->status === RouteStatus::Found;
    }
}
