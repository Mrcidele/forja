<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Validation;

use Forja\Validation\Rule\Max;
use Forja\Validation\Rule\Min;

final class SearchQuery
{
    #[Min(1)]
    public int $page = 1;

    #[Max(100)]
    public int $perPage = 15;

    public ?string $term = null;
}
