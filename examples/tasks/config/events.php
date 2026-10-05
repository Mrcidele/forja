<?php

declare(strict_types=1);

use App\Events\TaskCompleted;
use App\Listeners\LogCompletedTask;

return [
    'listeners' => [
        TaskCompleted::class => [LogCompletedTask::class],
    ],
];
