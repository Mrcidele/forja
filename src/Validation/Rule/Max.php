<?php

declare(strict_types=1);

namespace Forja\Validation\Rule;

use Attribute;

/**
 * Máximo: caracteres para textos, valor para números e itens para listas.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class Max implements RuleInterface
{
    public function __construct(
        private int|float $max,
        private ?string $message = null,
    ) {
    }

    public function validate(string $field, mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_string($value) => mb_strlen($value) <= $this->max ? null : $this->message ?? sprintf('O campo %s deve ter no máximo %s caracteres.', $field, $this->max),
            is_int($value), is_float($value) => $value <= $this->max ? null : $this->message ?? sprintf('O campo %s deve ser no máximo %s.', $field, $this->max),
            is_array($value) => count($value) <= $this->max ? null : $this->message ?? sprintf('O campo %s deve ter no máximo %s itens.', $field, $this->max),
            default => null,
        };
    }
}
