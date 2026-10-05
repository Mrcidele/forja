<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Routing\Controllers;

use Forja\Http\Exception\NotFoundHttpException;
use Forja\Routing\Attribute\Group;
use Forja\Routing\Attribute\Route;
use Forja\Tests\Fixtures\Routing\UserRepository;
use Psr\Http\Message\ServerRequestInterface;

#[Group(prefix: '/users', name: 'users.')]
final readonly class UserController
{
    public function __construct(private UserRepository $users)
    {
    }

    /**
     * @return array<int, string>
     */
    #[Route('/', name: 'index')]
    public function index(): array
    {
        return $this->users->all();
    }

    /**
     * @return array{id: int, name: string, path: string}
     */
    #[Route('/{id:int}', name: 'show')]
    public function show(int $id, ServerRequestInterface $request): array
    {
        $name = $this->users->find($id) ?? throw new NotFoundHttpException();

        return ['id' => $id, 'name' => $name, 'path' => $request->getUri()->getPath()];
    }

    #[Route('/', methods: ['POST'], name: 'store')]
    #[Route('/novo', methods: 'POST')]
    public function store(): string
    {
        return 'criado';
    }

    public function helper(): string
    {
        return 'sem rota';
    }
}
