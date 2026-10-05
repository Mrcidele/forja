<?php

declare(strict_types=1);

use Forja\Routing\Exception\InvalidRouteException;
use Forja\Routing\Exception\RouteNotFoundException;
use Forja\Routing\Route;
use Forja\Routing\RouteCollection;
use Forja\Routing\Router;
use Forja\Routing\RouteStatus;
use Forja\Tests\Fixtures\Routing\Status;
use Nyholm\Psr7\ServerRequest;

function routerWith(Closure $define): Router
{
    $router = new Router();
    $define($router->routes());

    return $router;
}

$handler = static fn (): string => 'ok';

it('casa rotas estáticas', function () use ($handler): void {
    $router = routerWith(fn (RouteCollection $r): \Forja\Routing\Route => $r->get('/sobre', $handler, 'sobre'));

    $result = $router->match(new ServerRequest('GET', '/sobre'));

    expect($result->isFound())->toBeTrue()
        ->and($result->route?->name)->toBe('sobre')
        ->and($result->parameters)->toBe([]);
});

it('casa rotas dinâmicas e converte parâmetros {x:int}', function () use ($handler): void {
    $router = routerWith(fn (RouteCollection $r): \Forja\Routing\Route => $r->get('/users/{id:int}/posts/{slug}', $handler));

    $result = $router->matchPath('GET', '/users/42/posts/ola-mundo');

    expect($result->isFound())->toBeTrue()
        ->and($result->parameters)->toBe(['id' => 42, 'slug' => 'ola-mundo']);
});

it('respeita a restrição de tipo dos parâmetros', function (string $path, bool $found) use ($handler): void {
    $router = routerWith(function (RouteCollection $r) use ($handler): void {
        $r->get('/n/{id:int}', $handler);
        $r->get('/s/{slug:slug}', $handler);
        $r->get('/u/{id:uuid}', $handler);
        $r->get('/a/{name:alpha}', $handler);
        $r->get('/files/{path:any}', $handler);
        $r->get('/lang/{code:[a-z]{2}}', $handler);
    });

    expect($router->matchPath('GET', $path)->isFound())->toBe($found);
})->with([
    ['/n/10', true],
    ['/n/abc', false],
    ['/s/meu-post-1', true],
    ['/s/Meu_Post', false],
    ['/u/123e4567-e89b-12d3-a456-426614174000', true],
    ['/u/123', false],
    ['/a/forja', true],
    ['/a/f0rja', false],
    ['/files/docs/2026/a.pdf', true],
    ['/lang/pt', true],
    ['/lang/ptb', false],
]);

it('decodifica o caminho antes de casar', function () use ($handler): void {
    $router = routerWith(fn (RouteCollection $r): \Forja\Routing\Route => $r->get('/busca/{termo}', $handler));

    expect($router->matchPath('GET', '/busca/caf%C3%A9')->parameters)->toBe(['termo' => 'café']);
});

it('ignora barras finais e duplicadas', function () use ($handler): void {
    $router = routerWith(fn (RouteCollection $r): \Forja\Routing\Route => $r->get('users/', $handler));

    expect($router->matchPath('GET', '/users/')->isFound())->toBeTrue()
        ->and($router->matchPath('GET', '//users')->isFound())->toBeTrue();
});

it('prioriza rotas estáticas sobre dinâmicas', function (): void {
    $router = routerWith(function (RouteCollection $r): void {
        $r->get('/users/{id}', static fn (): string => 'dinâmica', 'dinamica');
        $r->get('/users/me', static fn (): string => 'estática', 'estatica');
    });

    expect($router->matchPath('GET', '/users/me')->route?->name)->toBe('estatica')
        ->and($router->matchPath('GET', '/users/7')->route?->name)->toBe('dinamica');
});

it('retorna 404 quando nenhuma rota casa', function () use ($handler): void {
    $router = routerWith(fn (RouteCollection $r): \Forja\Routing\Route => $r->get('/a', $handler));

    expect($router->matchPath('GET', '/b')->status)->toBe(RouteStatus::NotFound);
});

it('retorna 405 com os métodos permitidos', function () use ($handler): void {
    $router = routerWith(function (RouteCollection $r) use ($handler): void {
        $r->get('/items/{id}', $handler);
        $r->put('/items/{id}', $handler);
        $r->delete('/items/{id}', $handler);
    });

    $result = $router->matchPath('POST', '/items/1');

    expect($result->status)->toBe(RouteStatus::MethodNotAllowed)
        ->and($result->allowedMethods)->toBe(['DELETE', 'GET', 'PUT']);
});

it('atende HEAD com rotas GET', function () use ($handler): void {
    $router = routerWith(fn (RouteCollection $r): \Forja\Routing\Route => $r->get('/ping', $handler));

    expect($router->matchPath('HEAD', '/ping')->isFound())->toBeTrue();
});

it('aplica prefixos e nomes de grupos aninhados', function () use ($handler): void {
    $router = routerWith(function (RouteCollection $r) use ($handler): void {
        $r->group('/api', function (RouteCollection $r) use ($handler): void {
            $r->group('v1', function (RouteCollection $r) use ($handler): void {
                $r->get('/users/{id:int}', $handler, 'users.show');
            }, 'v1.');
            $r->post('/login', $handler, 'login');
        }, 'api.');
        $r->get('/fora', $handler, 'fora');
    });

    expect($router->matchPath('GET', '/api/v1/users/5')->route?->name)->toBe('api.v1.users.show')
        ->and($router->matchPath('POST', '/api/login')->route?->name)->toBe('api.login')
        ->and($router->matchPath('GET', '/fora')->route?->name)->toBe('fora');
});

it('funciona com mais rotas do que cabem em um bloco de regex', function () use ($handler): void {
    $router = routerWith(function (RouteCollection $r) use ($handler): void {
        for ($i = 0; $i < 100; $i++) {
            $r->get("/r{$i}/{id:int}", $handler, "r{$i}");
        }
    });

    $result = $router->matchPath('GET', '/r87/3');

    expect($result->route?->name)->toBe('r87')
        ->and($result->parameters)->toBe(['id' => 3]);
});

it('recompila quando novas rotas são adicionadas', function () use ($handler): void {
    $router = new Router();
    $router->routes()->get('/a', $handler);
    $router->compiled();
    $router->routes()->get('/b', $handler);

    expect($router->matchPath('GET', '/b')->isFound())->toBeTrue();
});

it('rejeita nomes e rotas estáticas duplicados', function (Closure $define, string $message): void {
    expect(fn (): \Forja\Routing\CompiledRoutes => routerWith($define)->compiled())->toThrow(InvalidRouteException::class, $message);
})->with([
    'nome' => [fn (RouteCollection $r): array => [$r->get('/a', 'A', 'x'), $r->get('/b', 'B', 'x')], 'já está em uso'],
    'rota' => [fn (RouteCollection $r): array => [$r->get('/a', 'A'), $r->get('/a', 'B')], 'já foi registrada'],
]);

it('exige ao menos um método HTTP', function (): void {
    new Route([], '/a', 'A');
})->throws(InvalidRouteException::class);

describe('geração de URLs', function () use ($handler): void {
    beforeEach(function () use ($handler): void {
        $this->router = routerWith(function (RouteCollection $r) use ($handler): void {
            $r->get('/', $handler, 'home');
            $r->get('/users/{id:int}', $handler, 'users.show');
            $r->get('/tasks/{status}', $handler, 'tasks');
            $r->get('/files/{path:any}', $handler, 'files');
        });
    });

    it('gera caminhos com parâmetros e query string', function (): void {
        expect($this->router->url('home'))->toBe('/')
            ->and($this->router->url('users.show', ['id' => 5]))->toBe('/users/5')
            ->and($this->router->url('users.show', ['id' => 5, 'tab' => 'posts', 'page' => 2]))->toBe('/users/5?tab=posts&page=2')
            ->and($this->router->url('tasks', ['status' => Status::Active]))->toBe('/tasks/active')
            ->and($this->router->url('tasks', ['status' => 'em andamento']))->toBe('/tasks/em%20andamento')
            ->and($this->router->url('files', ['path' => 'docs/a b.pdf']))->toBe('/files/docs/a%20b.pdf');
    });

    it('falha com nome desconhecido, parâmetro ausente ou inválido', function (string $name, array $parameters, string $message): void {
        expect(fn () => $this->router->url($name, $parameters))->toThrow(RouteNotFoundException::class, $message);
    })->with([
        ['inexistente', [], 'não encontrada'],
        ['users.show', [], 'obrigatório'],
        ['users.show', ['id' => 'abc'], 'não é válido'],
    ]);
});
