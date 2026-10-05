<?php

declare(strict_types=1);

namespace Forja\Http\Middleware;

use Closure;
use Forja\Http\Exception\TooManyRequestsHttpException;
use Forja\RateLimit\RateLimiter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Limita o número de requisições por cliente numa janela de tempo e informa
 * o consumo nos headers X-RateLimit-*. Acima do limite responde 429.
 */
final readonly class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var Closure(ServerRequestInterface): string */
    private Closure $key;

    /**
     * @param (Closure(ServerRequestInterface): string)|null $key identifica o cliente; padrão: IP
     */
    public function __construct(
        private RateLimiter $limiter,
        private int $maxAttempts = 60,
        private int $decaySeconds = 60,
        ?Closure $key = null,
    ) {
        $this->key = $key ?? static function (ServerRequestInterface $request): string {
            $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'desconhecido';

            return is_string($ip) ? $ip : 'desconhecido';
        };
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $limit = $this->limiter->hit(($this->key)($request), $this->maxAttempts, $this->decaySeconds);
        $headers = [
            'X-RateLimit-Limit' => (string) $limit->limit,
            'X-RateLimit-Remaining' => (string) $limit->remaining(),
            'X-RateLimit-Reset' => (string) $limit->resetsAt,
        ];

        if ($limit->exceeded()) {
            throw new TooManyRequestsHttpException($limit->retryAfter($this->limiter->now()), $headers);
        }

        $response = $handler->handle($request);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
