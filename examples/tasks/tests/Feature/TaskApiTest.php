<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->app = app();
    request($this->app, 'POST', '/api/projects', ['name' => 'Forja']);
});

it('cria e consulta tarefas', function (): void {
    $created = request($this->app, 'POST', '/api/tasks', ['title' => 'Escrever a documentação', 'projectId' => 1, 'dueDate' => '2026-12-01']);

    expect($created->getStatusCode())->toBe(201)
        ->and($created->getHeaderLine('Location'))->toBe('/api/tasks/1')
        ->and(json($created)['data'])->toMatchArray(['id' => 1, 'titulo' => 'Escrever a documentação', 'status' => 'pendente', 'prazo' => '2026-12-01']);

    $shown = json(request($this->app, 'GET', '/api/tasks/1'))['data'];

    expect($shown['projeto'])->toBe(['id' => 1, 'nome' => 'Forja']);
});

it('valida os dados enviados', function (): void {
    $response = request($this->app, 'POST', '/api/tasks', ['title' => 'x', 'status' => 'arquivada']);

    expect($response->getStatusCode())->toBe(422)
        ->and(json($response)['error']['errors'])->toBe([
            'title' => ['O campo title deve ter pelo menos 3 caracteres.'],
            'projectId' => ['O campo projectId é obrigatório.'],
            'status' => ['O campo status deve ser um destes valores: pendente, em_andamento, concluida.'],
        ])
        ->and(request($this->app, 'POST', '/api/tasks', ['title' => 'Sem projeto', 'projectId' => 99])->getStatusCode())->toBe(422);
});

it('lista com filtros e paginação', function (): void {
    foreach (['Um', 'Dois', 'Três'] as $index => $title) {
        request($this->app, 'POST', '/api/tasks', ['title' => "Tarefa {$title}", 'projectId' => 1, 'status' => $index === 0 ? 'concluida' : 'pendente']);
    }

    $page = json(request($this->app, 'GET', '/api/tasks?perPage=2&page=1'));
    $done = json(request($this->app, 'GET', '/api/tasks?status=concluida'));

    expect(array_column($page['data'], 'titulo'))->toBe(['Tarefa Três', 'Tarefa Dois'])
        ->and($page['meta'])->toBe(['total' => 3, 'per_page' => 2, 'current_page' => 1, 'last_page' => 2])
        ->and(array_column($done['data'], 'titulo'))->toBe(['Tarefa Um'])
        ->and(request($this->app, 'GET', '/api/tasks?perPage=500')->getStatusCode())->toBe(422);
});

it('atualiza, conclui com evento registrado no log e remove tarefas', function (): void {
    request($this->app, 'POST', '/api/tasks', ['title' => 'Publicar no Packagist', 'projectId' => 1]);

    $updated = json(request($this->app, 'PATCH', '/api/tasks/1', ['status' => 'concluida']))['data'];

    expect($updated['status'])->toBe('concluida')
        ->and((string) file_get_contents(dirname(__DIR__, 2) . '/var/logs/app.log'))->toContain('Tarefa 1 concluída: Publicar no Packagist')
        ->and(request($this->app, 'DELETE', '/api/tasks/1')->getStatusCode())->toBe(204)
        ->and(request($this->app, 'GET', '/api/tasks/1')->getStatusCode())->toBe(404)
        ->and(request($this->app, 'GET', '/api/tasks/abc')->getStatusCode())->toBe(404);
});

it('lista projetos com as tarefas e impede nomes duplicados', function (): void {
    request($this->app, 'POST', '/api/tasks', ['title' => 'Primeira', 'projectId' => 1]);

    expect(json(request($this->app, 'GET', '/api/projects'))['data'][0]['tarefas'][0]['titulo'])->toBe('Primeira')
        ->and(request($this->app, 'POST', '/api/projects', ['name' => 'Forja'])->getStatusCode())->toBe(409);
});

it('aplica CORS e rate limit na API', function (): void {
    $response = request($this->app, 'GET', '/api/tasks', null, ['Origin' => 'https://cliente.test']);

    expect($response->getHeaderLine('Access-Control-Allow-Origin'))->toBe('*')
        ->and($response->getHeaderLine('X-RateLimit-Limit'))->toBe('120');
});
