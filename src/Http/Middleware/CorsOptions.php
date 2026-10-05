<?php

declare(strict_types=1);

namespace Forja\Http\Middleware;

/**
 * Política de CORS. Origens aceitam "*" (qualquer uma) ou curingas como
 * "https://*.forja.dev".
 */
final readonly class CorsOptions
{
    /**
     * @param list<string> $allowedOrigins
     * @param list<string> $allowedMethods
     * @param list<string> $allowedHeaders "*" reflete os headers pedidos no preflight
     * @param list<string> $exposedHeaders
     */
    public function __construct(
        public array $allowedOrigins = ['*'],
        public array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        public array $allowedHeaders = ['*'],
        public array $exposedHeaders = [],
        public bool $allowCredentials = false,
        public int $maxAge = 0,
    ) {
    }

    public function allowsOrigin(string $origin): bool
    {
        foreach ($this->allowedOrigins as $allowed) {
            if ($allowed === '*' || $allowed === $origin) {
                return true;
            }

            if (str_contains($allowed, '*')) {
                $pattern = '#^' . str_replace('\*', '[^/]*', preg_quote($allowed, '#')) . '$#i';

                if (preg_match($pattern, $origin) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    public function allowsAnyOrigin(): bool
    {
        return in_array('*', $this->allowedOrigins, true);
    }
}
