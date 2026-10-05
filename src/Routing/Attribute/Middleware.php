<?php

declare(strict_types=1);

namespace Forja\Routing\Attribute;

use Attribute;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Adiciona middlewares às rotas de um controller (na classe) ou de um método.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Middleware
{
    /** @var list<class-string<MiddlewareInterface>> */
    public array $middleware;

    /**
     * @param class-string<MiddlewareInterface> ...$middleware
     */
    public function __construct(string ...$middleware)
    {
        $this->middleware = array_values($middleware);
    }
}
