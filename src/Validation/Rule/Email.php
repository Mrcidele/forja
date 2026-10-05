<?php

declare(strict_types=1);

namespace Forja\Validation\Rule;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class Email implements RuleInterface
{
    public function __construct(
        private ?string $message = null,
    ) {
    }

    public function validate(string $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            ? null
            : ($this->message ?? sprintf('O campo %s deve ser um e-mail válido.', $field));
    }
}
