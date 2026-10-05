<?php

declare(strict_types=1);

namespace Forja\Routing\Attribute;

use Attribute;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Aplica prefixo de caminho, prefixo de nome e middlewares a todas as rotas de um controller.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Group
{
    /**
     * @param list<class-string<MiddlewareInterface>> $middleware
     */
    public function __construct(
        public string $prefix = '',
        public string $name = '',
        public array $middleware = [],
    ) {
    }
}
