<?php

declare(strict_types=1);

namespace Forja\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Ponto de entrada HTTP da aplicação: recebe a requisição e devolve a resposta.
 */
final readonly class Kernel implements RequestHandlerInterface
{
    public function __construct(
        private RequestHandlerInterface $handler,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handler->handle($request);
    }
}
