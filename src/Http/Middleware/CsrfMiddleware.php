<?php

declare(strict_types=1);

namespace Forja\Http\Middleware;

use Forja\Http\Exception\ForbiddenHttpException;
use Forja\Session\Session;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Proteção CSRF por token de sessão (synchronizer token).
 *
 * Requisições que alteram estado precisam enviar o token no campo do
 * formulário ou no header X-CSRF-Token. O token atual fica no atributo
 * "csrf_token" da requisição para ser usado nas views. Requer o
 * SessionMiddleware antes dele.
 */
final readonly class CsrfMiddleware implements MiddlewareInterface
{
    public const string ATTRIBUTE = 'csrf_token';

    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

    /**
     * @param list<string> $except caminhos ignorados; aceita curinga, como "/webhooks/*"
     */
    public function __construct(
        private array $except = [],
        private string $field = '_token',
        private string $header = 'X-CSRF-Token',
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $request->getAttribute(Session::class);

        if (! $session instanceof Session) {
            throw new LogicException('O CsrfMiddleware precisa do SessionMiddleware antes dele.');
        }

        $token = $session->token();
        $request = $request->withAttribute(self::ATTRIBUTE, $token);

        if (! in_array($request->getMethod(), self::SAFE_METHODS, true) && ! $this->isExcluded($request)) {
            $provided = $this->providedToken($request);

            if ($provided === null || ! hash_equals($token, $provided)) {
                throw new ForbiddenHttpException('Token CSRF inválido ou ausente.');
            }
        }

        return $handler->handle($request);
    }

    private function providedToken(ServerRequestInterface $request): ?string
    {
        $body = $request->getParsedBody();

        if (is_array($body) && is_string($body[$this->field] ?? null)) {
            return $body[$this->field];
        }

        $header = $request->getHeaderLine($this->header);

        return $header !== '' ? $header : null;
    }

    private function isExcluded(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        foreach ($this->except as $pattern) {
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';

            if (preg_match($regex, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
