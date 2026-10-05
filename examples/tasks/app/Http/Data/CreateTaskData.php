<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Entity\TaskStatus;
use DateTimeImmutable;
use Forja\Validation\Rule\Max;
use Forja\Validation\Rule\Min;
use Forja\Validation\Rule\Required;

final readonly class CreateTaskData
{
    public function __construct(
        #[Required, Min(3), Max(120)] public string $title,
        #[Required, Min(1)] public int $projectId,
        public TaskStatus $status = TaskStatus::Pending,
        public ?DateTimeImmutable $dueDate = null,
    ) {
    }
}
