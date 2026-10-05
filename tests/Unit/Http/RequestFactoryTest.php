<?php

declare(strict_types=1);

use Forja\Http\RequestFactory;

it('cria a requisição a partir de arrays no formato das superglobais', function (): void {
    $request = new RequestFactory()->fromArrays(
        server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/users?page=2', 'QUERY_STRING' => 'page=2', 'HTTP_HOST' => 'forja.test'],
        headers: ['Content-Type' => 'application/json'],
        cookies: ['session' => 'abc'],
        query: ['page' => '2'],
        body: '{"name":"Caio"}',
    );

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('http://forja.test/users?page=2')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and($request->getCookieParams())->toBe(['session' => 'abc'])
        ->and($request->getQueryParams())->toBe(['page' => '2'])
        ->and((string) $request->getBody())->toBe('{"name":"Caio"}');
});

it('cria a requisição a partir das superglobais', function (): void {
    $backup = [$_SERVER, $_GET];
    $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/ping?x=1', 'HTTP_HOST' => 'localhost', 'HTTP_ACCEPT' => 'text/html'];
    $_GET = ['x' => '1'];

    try {
        $request = new RequestFactory()->fromGlobals();
    } finally {
        [$_SERVER, $_GET] = $backup;
    }

    expect($request->getMethod())->toBe('GET')
        ->and($request->getUri()->getPath())->toBe('/ping')
        ->and($request->getQueryParams())->toBe(['x' => '1'])
        ->and($request->getHeaderLine('Accept'))->toBe('text/html');
});
