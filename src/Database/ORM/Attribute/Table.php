<?php

declare(strict_types=1);

namespace Forja\Database\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Table
{
    public function __construct(
        public string $name,
    ) {
    }
}
