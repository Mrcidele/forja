<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Entity\TaskStatus;
use DateTimeImmutable;
use Forja\Validation\Rule\Max;
use Forja\Validation\Rule\Min;

/**
 * Atualização parcial (PATCH): só os campos enviados mudam.
 */
final readonly class UpdateTaskData
{
    public function __construct(
        #[Min(3), Max(120)] public ?string $title = null,
        public ?TaskStatus $status = null,
        public ?DateTimeImmutable $dueDate = null,
    ) {
    }
}
