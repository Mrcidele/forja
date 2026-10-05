<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Entity\Project;
use App\Http\Data\CreateProjectData;
use App\Http\Resources\ProjectResource;
use Forja\Database\ORM\EntityManager;
use Forja\Http\Exception\ConflictHttpException;
use Forja\Http\Resource\ResourceCollection;
use Forja\Routing\Attribute\FromBody;
use Forja\Routing\Attribute\Group;
use Forja\Routing\Attribute\Route;
use Psr\Http\Message\ResponseInterface;

#[Group(prefix: '/api/projects', name: 'projects.')]
final readonly class ProjectController
{
    public function __construct(
        private EntityManager $entities,
    ) {
    }

    #[Route('/', name: 'index')]
    public function index(): ResourceCollection
    {
        return ProjectResource::collection($this->entities->query(Project::class)->orderBy('name')->with('tasks')->get());
    }

    #[Route('/', methods: ['POST'], name: 'store')]
    public function store(#[FromBody] CreateProjectData $data): ResponseInterface
    {
        if ($this->entities->findOneBy(Project::class, ['name' => $data->name]) !== null) {
            throw new ConflictHttpException(sprintf('Já existe um projeto chamado "%s".', $data->name));
        }

        $project = new Project($data->name);
        $this->entities->save($project);

        return ProjectResource::make($project)->response(201);
    }
}
