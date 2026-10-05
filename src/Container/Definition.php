<?php

declare(strict_types=1);

namespace Forja\Container;

use Closure;

/**
 * Como uma entrada do container é construída.
 *
 * @internal
 */
final readonly class Definition
{
    /**
     * @param Closure|string $concrete closure de fábrica ou nome da classe/entrada a resolver
     */
    public function __construct(
        public Closure|string $concrete,
        public bool $shared,
    ) {
    }
}
