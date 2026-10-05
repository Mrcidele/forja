<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Entity\TaskStatus;
use Forja\Validation\Rule\Max;
use Forja\Validation\Rule\Min;

final readonly class TaskFilter
{
    public function __construct(
        public ?TaskStatus $status = null,
        public ?int $projectId = null,
        #[Min(1)] public int $page = 1,
        #[Min(1), Max(100)] public int $perPage = 20,
    ) {
    }
}
