<?php

declare(strict_types=1);

use Forja\Cache\ArrayCache;
use Forja\Http\CallableHandler;
use Forja\Http\Middleware\SessionMiddleware;
use Forja\Session\CacheSessionStore;
use Forja\Session\Session;
use Forja\Session\SessionOptions;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

beforeEach(function (): void {
    $this->store = new CacheSessionStore(new ArrayCache());
    $this->middleware = new SessionMiddleware($this->store, new SessionOptions(lifetime: 3600, secure: true));
});

/**
 * @param Closure(Session): void $action
 */
function withSession(SessionMiddleware $middleware, Closure $action, array $cookies = []): ResponseInterface
{
    $request = new ServerRequest('GET', '/')->withCookieParams($cookies);

    return $middleware->process($request, new CallableHandler(static function (ServerRequestInterface $request) use ($action): Response {
        $session = $request->getAttribute(Session::class);
        assert($session instanceof Session);
        $action($session);

        return new Response();
    }));
}

function sessionIdFrom(ResponseInterface $response): string
{
    preg_match('/forja_session=([a-f0-9]{40})/', $response->getHeaderLine('Set-Cookie'), $matches);

    return $matches[1] ?? '';
}

it('não cria cookie para sessões vazias', function (): void {
    $response = withSession($this->middleware, static fn (Session $session): null => null);

    expect($response->hasHeader('Set-Cookie'))->toBeFalse();
});

it('persiste a sessão e a recupera na requisição seguinte pelo cookie', function (): void {
    $response = withSession($this->middleware, static fn (Session $session) => $session->set('user', 42));
    $id = sessionIdFrom($response);

    expect($id)->not->toBe('')
        ->and($response->getHeaderLine('Set-Cookie'))->toContain('HttpOnly')->toContain('Secure')->toContain('SameSite=Lax')->toContain('Max-Age=3600');

    $user = null;
    withSession($this->middleware, static function (Session $session) use (&$user): void {
        $user = $session->get('user');
    }, ['forja_session' => $id]);

    expect($user)->toBe(42);
});

it('descarta IDs desconhecidos enviados pelo cliente', function (): void {
    $forged = str_repeat('a', 40);

    $response = withSession($this->middleware, static fn (Session $session) => $session->set('x', 1), ['forja_session' => $forged]);

    expect(sessionIdFrom($response))->not->toBe($forged);
});

it('apaga a sessão antiga ao regenerar o ID', function (): void {
    $id = sessionIdFrom(withSession($this->middleware, static fn (Session $session) => $session->set('user', 1)));

    $newId = sessionIdFrom(withSession($this->middleware, static fn (Session $session) => $session->regenerate(), ['forja_session' => $id]));

    expect($newId)->not->toBe($id)
        ->and($this->store->read($id))->toBeNull()
        ->and($this->store->read($newId))->toMatchArray(['user' => 1]);
});

it('expira o cookie quando a sessão é invalidada', function (): void {
    $id = sessionIdFrom(withSession($this->middleware, static fn (Session $session) => $session->set('user', 1)));

    $response = withSession($this->middleware, static fn (Session $session) => $session->invalidate(), ['forja_session' => $id]);

    expect($response->getHeaderLine('Set-Cookie'))->toContain('forja_session=;')->toContain('Max-Age=0')
        ->and($this->store->read($id))->toBeNull();
});
