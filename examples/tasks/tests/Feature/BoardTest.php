<?php

declare(strict_types=1);

use Forja\Http\Middleware\CsrfMiddleware;
use Nyholm\Psr7\ServerRequest;

it('exibe o quadro e cria tarefas pelo formulário com CSRF', function (): void {
    $app = app();
    request($app, 'POST', '/api/projects', ['name' => 'Site']);

    $page = $app->handle(new ServerRequest('GET', '/'));
    preg_match('/forja_session=([a-f0-9]{40})/', $page->getHeaderLine('Set-Cookie'), $cookie);
    preg_match('/name="_token" value="([a-f0-9]+)"/', (string) $page->getBody(), $token);

    expect($page->getStatusCode())->toBe(200)
        ->and((string) $page->getBody())->toContain('Quadro de tarefas')->toContain('<option value="1">Site</option>');

    $withoutToken = $app->handle(new ServerRequest('POST', '/tasks')->withCookieParams(['forja_session' => $cookie[1]])->withParsedBody(['title' => 'Sem token', 'projectId' => '1']));

    expect($withoutToken->getStatusCode())->toBe(403);

    $created = $app->handle(new ServerRequest('POST', '/tasks')
        ->withCookieParams(['forja_session' => $cookie[1]])
        ->withParsedBody(['_token' => $token[1], 'title' => 'Criar landing page', 'projectId' => '1']));

    expect($created->getStatusCode())->toBe(303)
        ->and($created->getHeaderLine('Location'))->toBe('/');

    $board = (string) $app->handle(new ServerRequest('GET', '/')->withCookieParams(['forja_session' => $cookie[1]]))->getBody();

    expect($board)->toContain('Tarefa &quot;Criar landing page&quot; criada.')->toContain('<strong>Criar landing page</strong>')
        ->and(CsrfMiddleware::ATTRIBUTE)->toBe('csrf_token');
});
