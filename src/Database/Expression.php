<?php

declare(strict_types=1);

namespace Forja\Database;

use Stringable;

/**
 * Trecho de SQL inserido sem escape (use apenas com valores confiáveis).
 */
final readonly class Expression implements Stringable
{
    public function __construct(
        public string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
