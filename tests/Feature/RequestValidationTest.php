<?php

declare(strict_types=1);

use Forja\Container\Container;
use Forja\Error\ExceptionHandler;
use Forja\Http\Kernel;
use Forja\Http\Middleware\ErrorHandlerMiddleware;
use Forja\Routing\Attribute\FromBody;
use Forja\Routing\Attribute\FromQuery;
use Forja\Routing\ControllerInvoker;
use Forja\Routing\RouteHandler;
use Forja\Routing\Router;
use Forja\Tests\Fixtures\Validation\CreateUserData;
use Forja\Tests\Fixtures\Validation\SearchQuery;
use Nyholm\Psr7\ServerRequest;

beforeEach(function (): void {
    $router = new Router();
    $router->routes()->post('/api/users', static fn (#[FromBody] CreateUserData $data): array => ['nome' => $data->name, 'idade' => $data->age]);
    $router->routes()->get('/api/search', static fn (#[FromQuery] SearchQuery $query): array => ['pagina' => $query->page, 'termo' => $query->term]);
    $router->routes()->post('/broken', static fn (#[FromBody] array $data): array => $data);

    $container = new Container();
    $this->kernel = new Kernel(
        new RouteHandler($router, new ControllerInvoker($container), $container),
        [new ErrorHandlerMiddleware(new ExceptionHandler())],
    );
});

function decode(Psr\Http\Message\ResponseInterface $response): array
{
    return (array) json_decode((string) $response->getBody(), true);
}

it('preenche o DTO a partir de um corpo JSON', function (): void {
    $request = new ServerRequest('POST', '/api/users', ['Content-Type' => 'application/json'], '{"name":"Ada","email":"ada@forja.test","age":"36"}');

    expect(decode($this->kernel->handle($request)))->toBe(['nome' => 'Ada', 'idade' => 36]);
});

it('preenche o DTO a partir de formulários e da query string', function (): void {
    $form = new ServerRequest('POST', '/api/users')->withParsedBody(['name' => 'Linus', 'email' => 'linus@forja.test']);
    $urlEncoded = new ServerRequest('POST', '/api/users', ['Content-Type' => 'application/x-www-form-urlencoded'], 'name=Grace&email=grace%40forja.test&age=40');
    $search = new ServerRequest('GET', '/api/search?page=2&term=php')->withQueryParams(['page' => '2', 'term' => 'php']);

    expect(decode($this->kernel->handle($form)))->toBe(['nome' => 'Linus', 'idade' => null])
        ->and(decode($this->kernel->handle($urlEncoded)))->toBe(['nome' => 'Grace', 'idade' => 40])
        ->and(decode($this->kernel->handle($search)))->toBe(['pagina' => 2, 'termo' => 'php']);
});

it('responde 422 com os erros de validação', function (): void {
    $response = $this->kernel->handle(new ServerRequest('POST', '/api/users', ['Content-Type' => 'application/json'], '{"name":"Al"}'));

    expect($response->getStatusCode())->toBe(422)
        ->and(decode($response)['error']['errors'])->toBe([
            'name' => ['O campo name deve ter pelo menos 3 caracteres.'],
            'email' => ['O campo email é obrigatório.'],
        ]);
});

it('responde 400 para JSON malformado', function (): void {
    $response = $this->kernel->handle(new ServerRequest('POST', '/api/users', ['Content-Type' => 'application/json'], '{"name":'));

    expect($response->getStatusCode())->toBe(400);
});

it('exige que o parâmetro seja uma classe', function (): void {
    $response = $this->kernel->handle(new ServerRequest('POST', '/broken'));

    expect($response->getStatusCode())->toBe(500);
});
