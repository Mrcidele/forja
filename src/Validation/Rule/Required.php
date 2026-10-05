<?php

declare(strict_types=1);

namespace Forja\Validation\Rule;

use Attribute;

/**
 * O campo precisa estar presente e não vazio (null, "" ou []).
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class Required implements RuleInterface
{
    public function __construct(
        private ?string $message = null,
    ) {
    }

    public function validate(string $field, mixed $value): ?string
    {
        $empty = $value === null || (is_string($value) && trim($value) === '') || $value === [];

        return $empty ? ($this->message ?? sprintf('O campo %s é obrigatório.', $field)) : null;
    }
}
