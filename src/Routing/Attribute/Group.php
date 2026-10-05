<?php

declare(strict_types=1);

namespace Forja\Routing\Attribute;

use Attribute;

/**
 * Aplica prefixo de caminho e de nome a todas as rotas de um controller.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Group
{
    public function __construct(
        public string $prefix = '',
        public string $name = '',
    ) {
    }
}
