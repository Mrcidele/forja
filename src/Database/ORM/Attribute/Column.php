<?php

declare(strict_types=1);

namespace Forja\Database\ORM\Attribute;

use Attribute;

/**
 * Mapeia a propriedade para uma coluna (por padrão, o nome em snake_case).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Column
{
    public function __construct(
        public ?string $name = null,
    ) {
    }
}
