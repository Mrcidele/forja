<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskStatus;
use App\Http\Data\CreateTaskData;
use App\Repositories\TaskRepository;
use Forja\Database\ORM\EntityManager;
use Forja\Http\Middleware\CsrfMiddleware;
use Forja\Http\ResponseFactory;
use Forja\Routing\Attribute\FromBody;
use Forja\Routing\Attribute\Route;
use Forja\Routing\Router;
use Forja\Session\Session;
use Forja\View\Engine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Quadro web das tarefas: formulário com CSRF e mensagem flash na sessão.
 */
final readonly class HomeController
{
    public function __construct(
        private Engine $views,
        private TaskRepository $tasks,
        private EntityManager $entities,
        private ResponseFactory $responses,
        private Router $router,
    ) {
    }

    #[Route('/', name: 'board')]
    public function board(ServerRequestInterface $request, Session $session): string
    {
        return $this->views->render('tasks.board', [
            'board' => $this->tasks->board(),
            'statuses' => TaskStatus::cases(),
            'projects' => $this->entities->query(Project::class)->orderBy('name')->get(),
            'status' => $session->get('status'),
            'csrf_token' => $request->getAttribute(CsrfMiddleware::ATTRIBUTE),
        ]);
    }

    #[Route('/tasks', methods: ['POST'], name: 'tasks.create')]
    public function create(#[FromBody] CreateTaskData $data, Session $session): ResponseInterface
    {
        $this->tasks->save(new Task($data->projectId, $data->title, $data->status, $data->dueDate));
        $session->flash('status', sprintf('Tarefa "%s" criada.', $data->title));

        return $this->responses->redirect($this->router->url('board'), 303);
    }

    /**
     * @return array{status: string}
     */
    #[Route('/api/health', name: 'health')]
    public function health(): array
    {
        return ['status' => 'ok'];
    }
}
