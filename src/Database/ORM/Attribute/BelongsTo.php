<?php

declare(strict_types=1);

namespace Forja\Database\ORM\Attribute;

use Attribute;

/**
 * Relação N:1: esta entidade guarda em $foreignKey a chave de $target.
 * A coluna $foreignKey também precisa estar mapeada com #[Column].
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class BelongsTo
{
    /**
     * @param class-string $target
     */
    public function __construct(
        public string $target,
        public string $foreignKey,
        public string $ownerKey = 'id',
    ) {
    }
}
