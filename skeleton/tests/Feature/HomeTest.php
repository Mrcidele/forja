<?php

declare(strict_types=1);

it('exibe a página inicial', function (): void {
    $response = get('/');

    expect($response->getStatusCode())->toBe(200)
        ->and((string) $response->getBody())->toContain('Sua aplicação está no ar.');
});

it('responde o health check em JSON', function (): void {
    expect((string) get('/api/health')->getBody())->toBe('{"status":"ok"}');
});

it('responde 404 em JSON para rotas de API inexistentes', function (): void {
    $response = get('/api/nada');

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getHeaderLine('Content-Type'))->toContain('application/json');
});
