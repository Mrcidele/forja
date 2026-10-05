<?php

declare(strict_types=1);

namespace Forja\Validation\Rule;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class Regex implements RuleInterface
{
    public function __construct(
        private string $pattern,
        private ?string $message = null,
    ) {
    }

    public function validate(string $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_scalar($value) && preg_match($this->pattern, (string) $value) === 1
            ? null
            : ($this->message ?? sprintf('O campo %s está em um formato inválido.', $field));
    }
}
