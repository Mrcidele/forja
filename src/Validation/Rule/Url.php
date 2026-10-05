<?php

declare(strict_types=1);

namespace Forja\Validation\Rule;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class Url implements RuleInterface
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

        $valid = is_string($value)
            && filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);

        return $valid ? null : ($this->message ?? sprintf('O campo %s deve ser uma URL válida.', $field));
    }
}
