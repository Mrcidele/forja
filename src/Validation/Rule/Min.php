<?php

declare(strict_types=1);

namespace Forja\Validation\Rule;

use Attribute;

/**
 * Mínimo: caracteres para textos, valor para números e itens para listas.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class Min implements RuleInterface
{
    public function __construct(
        private int|float $min,
        private ?string $message = null,
    ) {
    }

    public function validate(string $field, mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_string($value) => mb_strlen($value) >= $this->min ? null : $this->message ?? sprintf('O campo %s deve ter pelo menos %s caracteres.', $field, $this->min),
            is_int($value), is_float($value) => $value >= $this->min ? null : $this->message ?? sprintf('O campo %s deve ser no mínimo %s.', $field, $this->min),
            is_array($value) => count($value) >= $this->min ? null : $this->message ?? sprintf('O campo %s deve ter pelo menos %s itens.', $field, $this->min),
            default => null,
        };
    }
}
