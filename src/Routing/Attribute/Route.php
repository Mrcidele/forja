<?php

declare(strict_types=1);

namespace Forja\Routing\Attribute;

use Attribute;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Declara uma rota em um método de controller (ou numa classe invocável).
 *
 * Parâmetros aceitam tipo ou regex: {id}, {id:int}, {slug:slug}, {code:[a-z]{2}}.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Route
{
    /** @var list<string> */
    public array $methods;

    /**
     * @param list<string>|string $methods
     * @param list<class-string<MiddlewareInterface>> $middleware
     */
    public function __construct(
        public string $path,
        array|string $methods = ['GET'],
        public ?string $name = null,
        public array $middleware = [],
    ) {
        $this->methods = is_string($methods) ? [$methods] : $methods;
    }
}
