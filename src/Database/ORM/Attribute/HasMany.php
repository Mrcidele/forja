<?php

declare(strict_types=1);

namespace Forja\Database\ORM\Attribute;

use Attribute;

/**
 * Relação 1:N: a tabela de $target tem a coluna $foreignKey apontando para esta entidade.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class HasMany
{
    /**
     * @param class-string $target
     */
    public function __construct(
        public string $target,
        public string $foreignKey,
        public string $localKey = 'id',
    ) {
    }
}
