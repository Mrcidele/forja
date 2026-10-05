<?php

declare(strict_types=1);

use Forja\Http\CallableHandler;
use Forja\Http\Exception\ForbiddenHttpException;
use Forja\Http\Middleware\CsrfMiddleware;
use Forja\Session\Session;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

beforeEach(function (): void {
    $this->session = new Session(Session::generateId());
    $this->handler = new CallableHandler(static fn (ServerRequestInterface $request): Response => new Response(200, [], (string) $request->getAttribute(CsrfMiddleware::ATTRIBUTE)));
});

function csrfRequest(string $method, Session $session, string $path = '/form'): ServerRequestInterface
{
    return new ServerRequest($method, $path)->withAttribute(Session::class, $session);
}

it('libera métodos seguros e expõe o token como atributo', function (): void {
    $response = new CsrfMiddleware()->process(csrfRequest('GET', $this->session), $this->handler);

    expect((string) $response->getBody())->toBe($this->session->token());
});

it('aceita o token pelo corpo ou pelo header', function (): void {
    $middleware = new CsrfMiddleware();
    $token = $this->session->token();

    $viaBody = $middleware->process(csrfRequest('POST', $this->session)->withParsedBody(['_token' => $token]), $this->handler);
    $viaHeader = $middleware->process(csrfRequest('DELETE', $this->session)->withHeader('X-CSRF-Token', $token), $this->handler);

    expect($viaBody->getStatusCode())->toBe(200)
        ->and($viaHeader->getStatusCode())->toBe(200);
});

it('recusa tokens ausentes ou incorretos', function (?string $token): void {
    $request = csrfRequest('POST', $this->session);

    new CsrfMiddleware()->process($token === null ? $request : $request->withParsedBody(['_token' => $token]), $this->handler);
})->with([null, 'errado'])->throws(ForbiddenHttpException::class, 'Token CSRF');

it('ignora caminhos excluídos', function (): void {
    $response = new CsrfMiddleware(['/webhooks/*'])->process(csrfRequest('POST', $this->session, '/webhooks/stripe'), $this->handler);

    expect($response->getStatusCode())->toBe(200);
});

it('exige o SessionMiddleware', function (): void {
    new CsrfMiddleware()->process(new ServerRequest('POST', '/'), $this->handler);
})->throws(LogicException::class, 'SessionMiddleware');
