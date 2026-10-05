<?php

declare(strict_types=1);

use Forja\Container\Container;
use Forja\Http\CallableHandler;
use Forja\Http\Middleware\Pipeline;
use Forja\Tests\Fixtures\Middleware\AppendHeader;
use Forja\Tests\Fixtures\Middleware\First;
use Forja\Tests\Fixtures\Middleware\Second;
use Forja\Tests\Fixtures\Middleware\ShortCircuit;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

function traceHandler(): CallableHandler
{
    return new CallableHandler(static function (ServerRequestInterface $request): Response {
        $trace = $request->getAttribute('trace', []);

        return new Response(200, [], implode(',', is_array($trace) ? $trace : []));
    });
}

it('executa os middlewares em ordem e devolve a resposta pelo caminho inverso', function (): void {
    $pipeline = new Pipeline([new AppendHeader('a'), new AppendHeader('b')], traceHandler());

    $response = $pipeline->handle(new ServerRequest('GET', '/'));

    expect((string) $response->getBody())->toBe('a,b')
        ->and($response->getHeader('X-Trace'))->toBe(['b', 'a']);
});

it('resolve middlewares informados por classe pelo container', function (): void {
    $pipeline = new Pipeline([First::class, Second::class], traceHandler(), new Container());

    expect((string) $pipeline->handle(new ServerRequest('GET', '/'))->getBody())->toBe('first,second');
});

it('permite que um middleware interrompa a cadeia', function (): void {
    $pipeline = new Pipeline([new ShortCircuit(), new AppendHeader('nunca')], traceHandler());

    $response = $pipeline->handle(new ServerRequest('GET', '/'));

    expect($response->getStatusCode())->toBe(401)
        ->and($response->hasHeader('X-Trace'))->toBeFalse();
});

it('vai direto ao handler sem middlewares', function (): void {
    expect(new Pipeline([], traceHandler())->handle(new ServerRequest('GET', '/'))->getStatusCode())->toBe(200);
});

it('falha quando a classe não é um middleware', function (): void {
    new Pipeline([stdClass::class], traceHandler(), new Container())->handle(new ServerRequest('GET', '/')); // @phpstan-ignore argument.type
})->throws(InvalidArgumentException::class, 'não é um middleware');
