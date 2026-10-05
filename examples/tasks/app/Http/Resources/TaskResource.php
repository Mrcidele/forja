<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Entity\Task;
use Forja\Http\Resource\JsonResource;

final class TaskResource extends JsonResource
{
    public function toArray(): array
    {
        $task = $this->resource;
        assert($task instanceof Task);

        return [
            'id' => $task->id,
            'titulo' => $task->title,
            'status' => $task->status->value,
            'prazo' => $task->dueDate?->format('Y-m-d'),
            'projeto' => $task->project === null ? $task->projectId : ['id' => $task->project->id, 'nome' => $task->project->name],
            'criada_em' => $task->createdAt->format(DATE_ATOM),
        ];
    }
}
