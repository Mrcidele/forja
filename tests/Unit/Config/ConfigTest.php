<?php

declare(strict_types=1);

use Forja\Config\Config;

beforeEach(function (): void {
    $this->config = new Config([
        'app' => ['name' => 'Forja', 'debug' => true, 'workers' => 4],
        'database' => ['connections' => ['sqlite' => ['path' => ':memory:']]],
        'cache.prefix' => 'forja_',
    ]);
});

it('lê valores por notação de ponto', function (): void {
    expect($this->config->get('app.name'))->toBe('Forja')
        ->and($this->config->get('database.connections.sqlite.path'))->toBe(':memory:')
        ->and($this->config->get('database.connections'))->toBe(['sqlite' => ['path' => ':memory:']])
        ->and($this->config->get('cache.prefix'))->toBe('forja_')
        ->and($this->config->get('app.inexistente', 'padrão'))->toBe('padrão')
        ->and($this->config->has('app.debug'))->toBeTrue()
        ->and($this->config->has('app.nada'))->toBeFalse();
});

it('grava valores criando os níveis intermediários', function (): void {
    $this->config->set('mail.smtp.port', 587);
    $this->config->set('app.name', 'Outro');

    expect($this->config->get('mail'))->toBe(['smtp' => ['port' => 587]])
        ->and($this->config->get('app.name'))->toBe('Outro')
        ->and($this->config->get('app.workers'))->toBe(4);
});

it('oferece getters tipados', function (): void {
    expect($this->config->string('app.name'))->toBe('Forja')
        ->and($this->config->bool('app.debug'))->toBeTrue()
        ->and($this->config->int('app.workers'))->toBe(4)
        ->and($this->config->array('app'))->toHaveKey('name')
        ->and($this->config->string('app.ausente', 'padrão'))->toBe('padrão');
});

it('falha quando o tipo não confere', function (): void {
    $this->config->int('app.name');
})->throws(InvalidArgumentException::class, 'A configuração [app.name] deveria ser int, mas é string.');
