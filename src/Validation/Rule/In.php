<?php

declare(strict_types=1);

namespace Forja\Validation\Rule;

use Attribute;
use BackedEnum;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class In implements RuleInterface
{
    /**
     * @param list<scalar> $values
     */
    public function __construct(
        private array $values,
        private ?string $message = null,
    ) {
    }

    public function validate(string $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = $value instanceof BackedEnum ? $value->value : $value;

        return in_array($value, $this->values, true)
            ? null
            : ($this->message ?? sprintf('O campo %s deve ser um destes valores: %s.', $field, implode(', ', array_map(strval(...), $this->values))));
    }
}
