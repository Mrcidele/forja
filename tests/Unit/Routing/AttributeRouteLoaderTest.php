<?php

declare(strict_types=1);

use Forja\Routing\AttributeRouteLoader;
use Forja\Routing\Route;
use Forja\Routing\RouteCollection;
use Forja\Tests\Fixtures\Routing\Controllers\Admin\FilterController;
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
        'GET /posts/{slug:slug} posts.show ' . ShowPostController::class,
        'GET /users users.index ' . UserController::class . '@index',
        'GET /users/{id:int} users.show ' . UserController::class . '@show',
        'POST /users users.store ' . UserController::class . '@store',
        'POST /users/novo - ' . UserController::class . '@store',
    ]);
});

it('ignora diretórios inexistentes', function (): void {
    $routes = new RouteCollection();
    new AttributeRouteLoader()->loadDirectory($routes, '/caminho/que/nao/existe');

    expect($routes)->toHaveCount(0);
});
