<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Validation;

use Forja\Validation\Rule\Regex;
use Forja\Validation\Rule\Required;

final readonly class AddressData
{
    public function __construct(
        #[Required] public string $city,
        #[Regex('/^\d{5}-?\d{3}$/')] public string $zip,
    ) {
    }
}
