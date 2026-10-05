<?php

declare(strict_types=1);

namespace Forja\Http\Middleware;

use Forja\Http\CallableHandler;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Executa uma lista de middlewares PSR-15 em sequência, terminando no handler final.
 *
 * Middlewares podem ser instâncias ou nomes de classe; nomes são resolvidos
 * pelo container apenas quando a execução chega até eles.
 */
final readonly class Pipeline implements RequestHandlerInterface
{
    /**
     * @param list<MiddlewareInterface|class-string<MiddlewareInterface>> $middleware
     */
    public function __construct(
        private array $middleware,
        private RequestHandlerInterface $handler,
        private ?ContainerInterface $container = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handlerAt(0)->handle($request);
    }

    private function handlerAt(int $index): RequestHandlerInterface
    {
        if (! isset($this->middleware[$index])) {
            return $this->handler;
        }

        return new CallableHandler(
            fn (ServerRequestInterface $request): ResponseInterface => $this->resolve($this->middleware[$index])
                ->process($request, $this->handlerAt($index + 1)),
        );
    }

    /**
     * @param MiddlewareInterface|class-string<MiddlewareInterface> $middleware
     */
    private function resolve(MiddlewareInterface|string $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        $instance = $this->container?->get($middleware);

        if (! $instance instanceof MiddlewareInterface) {
            throw new InvalidArgumentException(sprintf('[%s] não é um middleware PSR-15 resolvível.', $middleware));
        }

        return $instance;
    }
}
