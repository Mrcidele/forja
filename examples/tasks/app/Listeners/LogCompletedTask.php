<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TaskCompleted;
use Psr\Log\LoggerInterface;

final readonly class LogCompletedTask
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(TaskCompleted $event): void
    {
        $this->logger->info('Tarefa {id} concluída: {title}', ['id' => $event->task->id, 'title' => $event->task->title]);
    }
}
