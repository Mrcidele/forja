<?php

declare(strict_types=1);

use Forja\Container\Container;
use Forja\Http\Kernel;
use Forja\Routing\AttributeRouteLoader;
use Forja\Routing\ControllerInvoker;
use Forja\Routing\RouteHandler;
use Forja\Routing\Router;
use Nyholm\Psr7\ServerRequest;

it('atende uma requisição de ponta a ponta com rotas por atributos', function (): void {
    $container = new Container();
    $router = new Router();
    new AttributeRouteLoader()->loadDirectory($router->routes(), __DIR__ . '/../Fixtures/Routing/Controllers');
    $router->routes()->get('/hello/{name}', static fn (string $name): string => "Olá, {$name}!");

    $kernel = new Kernel(new RouteHandler($router, new ControllerInvoker($container)));

    expect((string) $kernel->handle(new ServerRequest('GET', '/hello/Forja'))->getBody())->toBe('Olá, Forja!')
        ->and((string) $kernel->handle(new ServerRequest('GET', '/users'))->getBody())->toBe('{"1":"Ada","2":"Linus"}');
});
