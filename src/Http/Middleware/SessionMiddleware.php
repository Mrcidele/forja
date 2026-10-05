<?php

declare(strict_types=1);

namespace Forja\Http\Middleware;

use Forja\Http\Cookie;
use Forja\Session\Session;
use Forja\Session\SessionOptions;
use Forja\Session\SessionStoreInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Carrega a sessão a partir do cookie, disponibiliza-a como atributo
 * Session::class da requisição e a persiste ao final.
 *
 * IDs desconhecidos enviados pelo cliente são descartados (contra fixação de
 * sessão) e sessões vazias não geram cookie.
 */
final readonly class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionStoreInterface $store,
        private SessionOptions $options = new SessionOptions(),
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookieId = $request->getCookieParams()[$this->options->cookieName] ?? null;
        $data = null;

        if (is_string($cookieId) && Session::isValidId($cookieId)) {
            $data = $this->store->read($cookieId);
        }

        $persisted = $data !== null;
        $session = $persisted && is_string($cookieId) ? new Session($cookieId, $data) : new Session(Session::generateId());
        $session->ageFlashData();

        $response = $handler->handle($request->withAttribute(Session::class, $session));

        $previous = $session->previousIdToDestroy();

        if ($previous !== null && $persisted) {
            $this->store->destroy($previous);
        }

        if ($session->isEmpty()) {
            if (! $persisted) {
                return $response;
            }

            $this->store->destroy($session->id());

            return $response->withAddedHeader('Set-Cookie', Cookie::expired($this->options->cookieName, $this->options->path, $this->options->domain)->toHeader());
        }

        $this->store->write($session->id(), $session->all(), $this->options->lifetime);

        return $response->withAddedHeader('Set-Cookie', $this->cookie($session->id())->toHeader());
    }

    private function cookie(string $id): Cookie
    {
        return new Cookie(
            $this->options->cookieName,
            $id,
            $this->options->lifetime,
            $this->options->path,
            $this->options->domain,
            $this->options->secure,
            $this->options->httpOnly,
            $this->options->sameSite,
        );
    }
}
