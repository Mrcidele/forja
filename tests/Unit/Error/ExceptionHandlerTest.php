<?php

declare(strict_types=1);

use Forja\Error\ExceptionHandler;
use Forja\Http\Exception\NotFoundHttpException;
use Forja\Http\Exception\TooManyRequestsHttpException;
use Forja\Http\Exception\UnprocessableEntityHttpException;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;

function json(ResponseInterface $response): array
{
    $data = json_decode((string) $response->getBody(), true);

    return is_array($data) ? $data : [];
}

it('detecta quando a resposta deve ser JSON', function (string $path, array $headers, bool $expected): void {
    expect(new ExceptionHandler()->wantsJson(new ServerRequest('GET', $path, $headers)))->toBe($expected);
})->with([
    'Accept JSON' => ['/pagina', ['Accept' => 'application/json'], true],
    'Accept problem+json' => ['/pagina', ['Accept' => 'application/problem+json'], true],
    'prefixo de API' => ['/api/users', [], true],
    'raiz da API' => ['/api', [], true],
    'parecido com API' => ['/apis', [], false],
    'HTML' => ['/pagina', ['Accept' => 'text/html'], false],
]);

it('responde JSON em produção sem detalhes internos', function (): void {
    $response = new ExceptionHandler()->render(new RuntimeException('senha do banco'), new ServerRequest('GET', '/api/x'));

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getHeaderLine('Content-Type'))->toBe('application/json; charset=utf-8')
        ->and(json($response))->toBe(['error' => ['status' => 500, 'message' => 'Erro interno do servidor.']]);
});

it('mantém status, headers e mensagem de HttpException', function (): void {
    $response = new ExceptionHandler()->render(new TooManyRequestsHttpException(30), new ServerRequest('GET', '/api/x'));

    expect($response->getStatusCode())->toBe(429)
        ->and($response->getHeaderLine('Retry-After'))->toBe('30')
        ->and(json($response)['error'])->toBe(['status' => 429, 'message' => 'Muitas requisições.']);
});

it('inclui os erros de validação no JSON', function (): void {
    $exception = new UnprocessableEntityHttpException(['email' => ['O campo email é obrigatório.']]);

    expect(json(new ExceptionHandler()->render($exception, new ServerRequest('POST', '/api/users')))['error']['errors'])
        ->toBe(['email' => ['O campo email é obrigatório.']]);
});

it('detalha a exceção no JSON em modo debug', function (): void {
    $error = json(new ExceptionHandler(debug: true)->render(new LogicException('detalhe'), new ServerRequest('GET', '/api/x')))['error'];

    expect($error['exception'])->toBe(LogicException::class)
        ->and($error['message'])->toBe('detalhe')
        ->and($error['file'])->toBe(__FILE__)
        ->and($error['trace'])->toBeArray()->not->toBeEmpty();
});

it('mostra a página de depuração com stack trace e trecho do código', function (): void {
    $previous = new InvalidArgumentException('causa original');
    $exception = new RuntimeException('Falhou <script>alert(1)</script>', 0, $previous);

    $html = (string) new ExceptionHandler(debug: true)->render($exception, new ServerRequest('GET', '/users?page=2', ['Accept' => 'text/html']))->getBody();

    expect($html)
        ->toContain('RuntimeException')
        ->toContain('Falhou &lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('Causada por InvalidArgumentException')
        ->toContain('causa original')
        ->toContain(__FILE__)
        ->toContain('Stack trace')
        ->toContain('class="line highlight"')
        ->toContain('/users?page=2');
});

it('mostra página genérica em produção', function (): void {
    $handler = new ExceptionHandler();

    $error = (string) $handler->render(new RuntimeException('segredo'), new ServerRequest('GET', '/'))->getBody();
    $notFound = $handler->render(new NotFoundHttpException(), new ServerRequest('GET', '/x'));

    expect($error)->toContain('500')->toContain('Erro interno do servidor.')->not->toContain('segredo')->not->toContain('Stack trace')
        ->and($notFound->getStatusCode())->toBe(404)
        ->and((string) $notFound->getBody())->toContain('<title>404 Not Found</title>')->toContain('Recurso não encontrado.');
});
