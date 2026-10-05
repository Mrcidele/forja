<?php

declare(strict_types=1);

use Forja\Cache\ArrayCache;
use Forja\Http\CallableHandler;
use Forja\Http\Exception\TooManyRequestsHttpException;
use Forja\Http\Middleware\RateLimitMiddleware;
use Forja\RateLimit\RateLimiter;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

beforeEach(function (): void {
    $this->now = 1_000;
    $clock = fn (): int => $this->now;
    $this->limiter = new RateLimiter(new ArrayCache($clock), $clock);
});

it('conta tentativas dentro da janela e reinicia depois dela', function (): void {
    $first = $this->limiter->hit('ip', 2, 60);
    $second = $this->limiter->hit('ip', 2, 60);
    $third = $this->limiter->hit('ip', 2, 60);

    expect($first->remaining())->toBe(1)
        ->and($second->remaining())->toBe(0)
        ->and($second->exceeded())->toBeFalse()
        ->and($third->exceeded())->toBeTrue()
        ->and($third->retryAfter(1_010))->toBe(50);

    $this->now = 1_060;

    expect($this->limiter->hit('ip', 2, 60)->attempts)->toBe(1);
});

it('mantém contadores separados por chave e permite limpá-los', function (): void {
    $this->limiter->hit('a', 1, 60);
    $this->limiter->hit('a', 1, 60);
    $this->limiter->clear('a');

    expect($this->limiter->hit('a', 1, 60)->exceeded())->toBeFalse()
        ->and($this->limiter->hit('b', 1, 60)->attempts)->toBe(1);
});

it('adiciona headers de consumo e responde 429 acima do limite', function (): void {
    $middleware = new RateLimitMiddleware($this->limiter, maxAttempts: 1, decaySeconds: 30);
    $request = new ServerRequest('GET', '/', [], null, '1.1', ['REMOTE_ADDR' => '10.0.0.1']);
    $handler = new CallableHandler(static fn (): Response => new Response());

    $response = $middleware->process($request, $handler);

    expect($response->getHeaderLine('X-RateLimit-Limit'))->toBe('1')
        ->and($response->getHeaderLine('X-RateLimit-Remaining'))->toBe('0')
        ->and($response->getHeaderLine('X-RateLimit-Reset'))->toBe('1030');

    try {
        $middleware->process($request, $handler);
        $this->fail('Deveria ter lançado 429.');
    } catch (TooManyRequestsHttpException $exception) {
        expect($exception->getStatusCode())->toBe(429)
            ->and($exception->getHeaders())->toMatchArray(['Retry-After' => '30', 'X-RateLimit-Remaining' => '0']);
    }

    $otherClient = new ServerRequest('GET', '/', [], null, '1.1', ['REMOTE_ADDR' => '10.0.0.2']);

    expect($middleware->process($otherClient, $handler)->getStatusCode())->toBe(200);
});
