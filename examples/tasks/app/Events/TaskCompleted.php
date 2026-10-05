<?php

declare(strict_types=1);

namespace App\Events;

use App\Entity\Task;

final readonly class TaskCompleted
{
    public function __construct(
        public Task $task,
    ) {
    }
}
