<?php

declare(strict_types=1);

namespace App\Http\Data;

use Forja\Validation\Rule\Max;
use Forja\Validation\Rule\Required;

final readonly class CreateProjectData
{
    public function __construct(
        #[Required, Max(80)] public string $name,
    ) {
    }
}
