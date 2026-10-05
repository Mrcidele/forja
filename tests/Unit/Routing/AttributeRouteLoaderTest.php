<?php

declare(strict_types=1);

use Forja\Routing\AttributeRouteLoader;
use Forja\Routing\Route;
use Forja\Routing\RouteCollection;
use Forja\Tests\Fixtures\Middleware\First;
use Forja\Tests\Fixtures\Middleware\Second;
use Forja\Tests\Fixtures\Middleware\Third;
use Forja\Tests\Fixtures\Routing\Controllers\Admin\FilterController;
use Forja\Tests\Fixtures\Routing\Controllers\ProtectedController;
use Forja\Tests\Fixtures\Routing\Controllers\ShowPostController;
use Forja\Tests\Fixtures\Routing\Controllers\UserController;

it('lê as rotas declaradas por atributos em um diretório', function (): void {
    $routes = new RouteCollection();
    new AttributeRouteLoader()->loadDirectory($routes, dirname(__DIR__, 2) . '/Fixtures/Routing/Controllers');

    $summary = array_map(
        static fn (Route $route): string => implode('|', $route->methods) . ' ' . $route->path . ' ' . ($route->name ?? '-') . ' ' . $route->handlerName(),
        $routes->all(),
    );

    expect($summary)->toBe([
        'GET /admin/tasks/{status}/{priority} admin.tasks ' . FilterController::class . '@tasks',
        'GET /admin/ratio/{value} - ' . FilterController::class . '@ratio',
        'DELETE /admin/empty - ' . FilterController::class . '@empty',
        'GET /protected/trace - ' . ProtectedController::class . '@trace',
        'GET /protected/method - ' . ProtectedController::class . '@method',
        'GET /posts/{slug:slug} posts.show ' . ShowPostController::class,
        'GET /users users.index ' . UserController::class . '@index',
        'GET /users/{id:int} users.show ' . UserController::class . '@show',
        'POST /users users.store ' . UserController::class . '@store',
        'POST /users/novo - ' . UserController::class . '@store',
    ]);
});

it('combina middlewares do grupo, da classe, do método e da rota', function (): void {
    $routes = new RouteCollection();
    new AttributeRouteLoader()->loadClass($routes, ProtectedController::class);

    expect($routes->all()[0]->middleware)->toBe([First::class, Second::class, Third::class])
        ->and($routes->all()[1]->middleware)->toBe([First::class, Second::class, Third::class]);
});

it('ignora diretórios inexistentes', function (): void {
    $routes = new RouteCollection();
    new AttributeRouteLoader()->loadDirectory($routes, '/caminho/que/nao/existe');

    expect($routes)->toHaveCount(0);
});
