<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskStatus;
use App\Events\TaskCompleted;
use App\Http\Data\CreateTaskData;
use App\Http\Data\TaskFilter;
use App\Http\Data\UpdateTaskData;
use App\Http\Resources\TaskResource;
use App\Repositories\TaskRepository;
use Forja\Database\ORM\EntityManager;
use Forja\Http\Exception\NotFoundHttpException;
use Forja\Http\Exception\UnprocessableEntityHttpException;
use Forja\Http\Middleware\RateLimitMiddleware;
use Forja\Http\Resource\ResourceCollection;
use Forja\Routing\Attribute\FromBody;
use Forja\Routing\Attribute\FromQuery;
use Forja\Routing\Attribute\Group;
use Forja\Routing\Attribute\Route;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;

#[Group(prefix: '/api/tasks', name: 'tasks.', middleware: [RateLimitMiddleware::class])]
final readonly class TaskController
{
    public function __construct(
        private TaskRepository $tasks,
        private EntityManager $entities,
        private EventDispatcherInterface $events,
    ) {
    }

    #[Route('/', name: 'index')]
    public function index(#[FromQuery] TaskFilter $filter): ResourceCollection
    {
        return TaskResource::collection($this->tasks->search($filter->status, $filter->projectId, $filter->page, $filter->perPage));
    }

    #[Route('/{id:int}', name: 'show')]
    public function show(int $id): TaskResource
    {
        return TaskResource::make($this->find($id));
    }

    #[Route('/', methods: ['POST'], name: 'store')]
    public function store(#[FromBody] CreateTaskData $data): ResponseInterface
    {
        if ($this->entities->find(Project::class, $data->projectId) === null) {
            throw new UnprocessableEntityHttpException(['projectId' => ['O projeto informado não existe.']]);
        }

        $task = new Task($data->projectId, $data->title, $data->status, $data->dueDate);
        $this->tasks->save($task);

        return TaskResource::make($task)->response(201, ['Location' => '/api/tasks/' . $task->id]);
    }

    #[Route('/{id:int}', methods: ['PATCH'], name: 'update')]
    public function update(int $id, #[FromBody] UpdateTaskData $data): TaskResource
    {
        $task = $this->find($id);
        $wasDone = $task->status === TaskStatus::Done;

        $task->title = $data->title ?? $task->title;
        $task->status = $data->status ?? $task->status;
        $task->dueDate = $data->dueDate ?? $task->dueDate;
        $this->tasks->save($task);

        if (! $wasDone && $task->status === TaskStatus::Done) {
            $this->events->dispatch(new TaskCompleted($task));
        }

        return TaskResource::make($task);
    }

    #[Route('/{id:int}', methods: ['DELETE'], name: 'destroy')]
    public function destroy(int $id): null
    {
        $this->tasks->delete($this->find($id));

        return null;
    }

    private function find(int $id): Task
    {
        $task = $this->tasks->find($id) ?? throw new NotFoundHttpException(sprintf('Tarefa %d não encontrada.', $id));
        $this->entities->load($task, 'project');

        return $task;
    }
}
