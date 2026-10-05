<?php

declare(strict_types=1);

use Forja\Http\CallableHandler;
use Forja\Http\Middleware\CorsMiddleware;
use Forja\Http\Middleware\CorsOptions;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

function okHandler(): CallableHandler
{
    return new CallableHandler(static fn (): Response => new Response(200, [], 'ok'));
}

it('não altera requisições sem Origin', function (): void {
    $response = new CorsMiddleware()->process(new ServerRequest('GET', '/'), okHandler());

    expect($response->hasHeader('Access-Control-Allow-Origin'))->toBeFalse();
});

it('libera qualquer origem com "*"', function (): void {
    $response = new CorsMiddleware()->process(new ServerRequest('GET', '/', ['Origin' => 'https://app.test']), okHandler());

    expect($response->getHeaderLine('Access-Control-Allow-Origin'))->toBe('*')
        ->and($response->hasHeader('Vary'))->toBeFalse();
});

it('ecoa a origem permitida quando há credenciais', function (): void {
    $middleware = new CorsMiddleware(new CorsOptions(['https://*.forja.dev'], exposedHeaders: ['X-Total'], allowCredentials: true));

    $allowed = $middleware->process(new ServerRequest('GET', '/', ['Origin' => 'https://admin.forja.dev']), okHandler());
    $denied = $middleware->process(new ServerRequest('GET', '/', ['Origin' => 'https://evil.test']), okHandler());

    expect($allowed->getHeaderLine('Access-Control-Allow-Origin'))->toBe('https://admin.forja.dev')
        ->and($allowed->getHeaderLine('Access-Control-Allow-Credentials'))->toBe('true')
        ->and($allowed->getHeaderLine('Access-Control-Expose-Headers'))->toBe('X-Total')
        ->and($allowed->getHeaderLine('Vary'))->toBe('Origin')
        ->and($denied->hasHeader('Access-Control-Allow-Origin'))->toBeFalse();
});

it('responde o preflight sem chamar o handler', function (): void {
    $middleware = new CorsMiddleware(new CorsOptions(['https://app.test'], ['GET', 'POST'], maxAge: 600));
    $request = new ServerRequest('OPTIONS', '/users', [
        'Origin' => 'https://app.test',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'Content-Type, Authorization',
    ]);

    $response = $middleware->process($request, new CallableHandler(static fn () => throw new LogicException('não deveria executar')));

    expect($response->getStatusCode())->toBe(204)
        ->and($response->getHeaderLine('Access-Control-Allow-Origin'))->toBe('https://app.test')
        ->and($response->getHeaderLine('Access-Control-Allow-Methods'))->toBe('GET, POST')
        ->and($response->getHeaderLine('Access-Control-Allow-Headers'))->toBe('Content-Type, Authorization')
        ->and($response->getHeaderLine('Access-Control-Max-Age'))->toBe('600');
});

it('responde o preflight de origem negada sem headers de CORS', function (): void {
    $middleware = new CorsMiddleware(new CorsOptions(['https://app.test']));
    $request = new ServerRequest('OPTIONS', '/', ['Origin' => 'https://evil.test', 'Access-Control-Request-Method' => 'GET']);

    $response = $middleware->process($request, okHandler());

    expect($response->getStatusCode())->toBe(204)
        ->and($response->hasHeader('Access-Control-Allow-Origin'))->toBeFalse();
});
