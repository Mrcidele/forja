<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entity\Task;
use App\Entity\TaskStatus;
use Forja\Database\ORM\Repository;
use Forja\Database\Query\Paginator;

/**
 * @extends Repository<Task>
 */
final class TaskRepository extends Repository
{
    /**
     * @return Paginator<Task>
     */
    public function search(?TaskStatus $status, ?int $projectId, int $page, int $perPage): Paginator
    {
        $query = $this->query()->with('project')->orderBy('id', 'desc');

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        return $query->paginate($perPage, $page);
    }

    /**
     * @return array<string, list<Task>> tarefas agrupadas pelo valor do status
     */
    public function board(): array
    {
        $board = array_fill_keys(array_map(static fn (TaskStatus $status): string => $status->value, TaskStatus::cases()), []);

        foreach ($this->query()->with('project')->orderBy('id')->get() as $task) {
            $board[$task->status->value][] = $task;
        }

        return $board;
    }

    protected function entity(): string
    {
        return Task::class;
    }
}
