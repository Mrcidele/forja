<?php

declare(strict_types=1);

use Forja\Routing\Exception\InvalidRouteException;
use Forja\Routing\RouteCache;
use Forja\Routing\Router;

beforeEach(function (): void {
    $this->file = sys_get_temp_dir() . '/forja-routes-' . bin2hex(random_bytes(4)) . '/routes.php';
});

afterEach(function (): void {
    if (is_file($this->file)) {
        unlink($this->file);
        rmdir(dirname($this->file));
    }
});

it('grava e recarrega o mapa compilado', function (): void {
    $router = new Router();
    $router->routes()->get('/users/{id:int}', ['App\UserController', 'show'], 'users.show');
    $router->routes()->post('/users', 'App\StoreUser', 'users.store');

    RouteCache::dump($router->compiled(), $this->file);
    $cached = Router::fromCompiled(RouteCache::load($this->file));

    $result = $cached->matchPath('GET', '/users/9');

    expect($result->isFound())->toBeTrue()
        ->and($result->route?->handler)->toBe(['App\UserController', 'show'])
        ->and($result->parameters)->toBe(['id' => 9])
        ->and($cached->matchPath('POST', '/users')->route?->handler)->toBe('App\StoreUser')
        ->and($cached->url('users.show', ['id' => 1]))->toBe('/users/1')
        ->and($cached->compiled())->toEqual($router->compiled());
});

it('recusa rotas com closures', function (): void {
    $router = new Router();
    $router->routes()->get('/a', static fn (): string => 'a');

    RouteCache::dump($router->compiled(), $this->file);
})->throws(InvalidRouteException::class, 'closure');
