<?php

declare(strict_types=1);

namespace Forja\Database\ORM\Attribute;

use Attribute;

/**
 * Marca a chave primária. Com $generated, o valor vem do banco (autoincremento).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Id
{
    public function __construct(
        public ?string $column = null,
        public bool $generated = true,
    ) {
    }
}
