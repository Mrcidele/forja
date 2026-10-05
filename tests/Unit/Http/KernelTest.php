<?php

declare(strict_types=1);

use Forja\Container\Container;
use Forja\Http\CallableHandler;
use Forja\Http\Kernel;
use Forja\Tests\Fixtures\Middleware\AppendHeader;
use Forja\Tests\Fixtures\Middleware\First;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

it('delega a requisição ao handler e devolve a resposta', function (): void {
    $kernel = new Kernel(new CallableHandler(
        static fn (ServerRequestInterface $request): Response => new Response(200, [], 'Olá, ' . $request->getUri()->getPath()),
    ));

    $response = $kernel->handle(new ServerRequest('GET', '/mundo'));

    expect($response->getStatusCode())->toBe(200)
        ->and((string) $response->getBody())->toBe('Olá, /mundo');
});

it('passa a requisição pelos middlewares globais antes do handler', function (): void {
    $kernel = new Kernel(
        new CallableHandler(static fn (ServerRequestInterface $request): Response => new Response(200, [], implode(',', (array) $request->getAttribute('trace')))),
        [new AppendHeader('global'), First::class],
        new Container(),
    );

    $response = $kernel->handle(new ServerRequest('GET', '/'));

    expect((string) $response->getBody())->toBe('global,first')
        ->and($response->getHeader('X-Trace'))->toBe(['first', 'global']);
});
