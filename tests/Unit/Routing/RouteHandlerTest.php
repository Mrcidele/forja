<?php

declare(strict_types=1);

use Forja\Container\Container;
use Forja\Http\Exception\MethodNotAllowedHttpException;
use Forja\Http\Exception\NotFoundHttpException;
use Forja\Routing\AttributeRouteLoader;
use Forja\Routing\ControllerInvoker;
use Forja\Routing\Route;
use Forja\Routing\RouteHandler;
use Forja\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

beforeEach(function (): void {
    $this->router = new Router();
    new AttributeRouteLoader()->loadDirectory($this->router->routes(), dirname(__DIR__, 2) . '/Fixtures/Routing/Controllers');
    $this->handler = new RouteHandler($this->router, new ControllerInvoker(new Container()));
});

function body(ResponseInterface $response): mixed
{
    return json_decode((string) $response->getBody(), true);
}

it('resolve o controller pelo container e injeta parâmetros e requisição', function (): void {
    $response = $this->handler->handle(new ServerRequest('GET', '/users/2'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeaderLine('Content-Type'))->toBe('application/json; charset=utf-8')
        ->and(body($response))->toBe(['id' => 2, 'name' => 'Linus', 'path' => '/users/2']);
});

it('executa controllers invocáveis e converte strings em HTML', function (): void {
    $response = $this->handler->handle(new ServerRequest('GET', '/posts/ola-forja'));

    expect((string) $response->getBody())->toBe('<h1>ola-forja</h1>')
        ->and($response->getHeaderLine('Content-Type'))->toBe('text/html; charset=utf-8');
});

it('converte parâmetros para enums e floats', function (): void {
    expect(body($this->handler->handle(new ServerRequest('GET', '/admin/tasks/archived/2'))))->toBe(['status' => 'archived', 'priority' => 2])
        ->and((string) $this->handler->handle(new ServerRequest('GET', '/admin/ratio/1.25'))->getBody())->toBe('2.5');
});

it('responde 404 para valores que não convertem no tipo declarado', function (string $path): void {
    $this->handler->handle(new ServerRequest('GET', $path));
})->with(['/admin/tasks/deleted/1', '/admin/tasks/active/9', '/admin/tasks/active/x', '/admin/ratio/abc'])
    ->throws(NotFoundHttpException::class, 'Valor inválido');

it('converte null em 204', function (): void {
    expect($this->handler->handle(new ServerRequest('DELETE', '/admin/empty'))->getStatusCode())->toBe(204);
});

it('lança 404 e 405 tipados', function (): void {
    expect(fn () => $this->handler->handle(new ServerRequest('GET', '/nada')))->toThrow(NotFoundHttpException::class)
        ->and(fn () => $this->handler->handle(new ServerRequest('PATCH', '/users')))->toThrow(MethodNotAllowedHttpException::class);

    try {
        $this->handler->handle(new ServerRequest('PATCH', '/users'));
    } catch (MethodNotAllowedHttpException $exception) {
        expect($exception->getStatusCode())->toBe(405)
            ->and($exception->getHeaders())->toBe(['Allow' => 'GET, POST'])
            ->and($exception->getAllowedMethods())->toBe(['GET', 'POST']);
    }
});

it('expõe a rota e os parâmetros como atributos da requisição', function (): void {
    $this->router->routes()->get('/attrs/{id:int}', static fn (ServerRequestInterface $request, Route $route): array => [
        'id' => $request->getAttribute('id'),
        'route' => $request->getAttribute(Route::class) === $route,
    ]);

    expect(body($this->handler->handle(new ServerRequest('GET', '/attrs/7'))))->toBe(['id' => 7, 'route' => true]);
});
