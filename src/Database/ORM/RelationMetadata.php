<?php

declare(strict_types=1);

namespace Forja\Database\ORM;

/**
 * @internal
 */
final readonly class RelationMetadata
{
    /**
     * @param 'hasMany'|'belongsTo' $type
     * @param class-string $target
     * @param string $localKey coluna desta entidade (hasMany) ou da entidade alvo (belongsTo)
     */
    public function __construct(
        public string $property,
        public string $type,
        public string $target,
        public string $foreignKey,
        public string $localKey,
    ) {
    }
}
