<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Database;

use Forja\Database\ORM\Attribute\Column;
use Forja\Database\ORM\Attribute\Id;
use Forja\Database\ORM\Attribute\Table;

/**
 * Entidade imutável com chave natural (não gerada).
 */
#[Table('tags')]
final readonly class Tag
{
    public function __construct(
        #[Id(generated: false)] public string $slug,
        #[Column] public string $label,
    ) {
    }
}
