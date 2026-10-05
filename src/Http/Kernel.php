<?php

declare(strict_types=1);

namespace Forja\Http;

use Forja\Http\Middleware\Pipeline;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Ponto de entrada HTTP da aplicação: recebe a requisição, passa pelos
 * middlewares globais e devolve a resposta do handler (normalmente o router).
 */
final readonly class Kernel implements RequestHandlerInterface
{
    private Pipeline $pipeline;

    /**
     * @param list<MiddlewareInterface|class-string<MiddlewareInterface>> $middleware middlewares globais, do mais externo ao mais interno
     */
    public function __construct(
        RequestHandlerInterface $handler,
        array $middleware = [],
        ?ContainerInterface $container = null,
    ) {
        $this->pipeline = new Pipeline($middleware, $handler, $container);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->pipeline->handle($request);
    }
}
