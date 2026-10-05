<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Entity\Project;
use App\Entity\Task;
use Forja\Http\Resource\JsonResource;

final class ProjectResource extends JsonResource
{
    public function toArray(): array
    {
        $project = $this->resource;
        assert($project instanceof Project);

        return [
            'id' => $project->id,
            'nome' => $project->name,
            'tarefas' => array_map(static fn (Task $task): array => ['id' => $task->id, 'titulo' => $task->title, 'status' => $task->status->value], $project->tasks),
        ];
    }
}
