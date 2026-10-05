<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Registra a ordem de execução no atributo "trace" e no header X-Trace.
 */
class AppendHeader implements MiddlewareInterface
{
    public function __construct(private readonly string $label = 'append')
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $trace = $request->getAttribute('trace', []);
        $trace = is_array($trace) ? $trace : [];
        $trace[] = $this->label;

        return $handler->handle($request->withAttribute('trace', $trace))->withAddedHeader('X-Trace', $this->label);
    }
}
