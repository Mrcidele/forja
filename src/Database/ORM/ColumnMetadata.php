<?php

declare(strict_types=1);

namespace Forja\Database\ORM;

/**
 * @internal
 */
final readonly class ColumnMetadata
{
    public function __construct(
        public string $property,
        public string $column,
        public ?string $type,
        public bool $nullable,
    ) {
    }
}
