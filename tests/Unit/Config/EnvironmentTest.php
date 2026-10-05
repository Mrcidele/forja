<?php

declare(strict_types=1);

use Forja\Config\AppConfig;
use Forja\Config\Config;
use Forja\Config\Environment;

it('interpreta nomes e apelidos de ambiente', function (string $name, Environment $expected): void {
    expect(Environment::fromName($name))->toBe($expected);
})->with([
    ['production', Environment::Production],
    ['PROD', Environment::Production],
    ['staging', Environment::Staging],
    ['local', Environment::Development],
    ['dev', Environment::Development],
    ['test', Environment::Testing],
]);

it('rejeita ambientes desconhecidos', function (): void {
    Environment::fromName('marte');
})->throws(ValueError::class, 'marte');

it('define o debug padrão por ambiente', function (): void {
    expect(Environment::Production->debugByDefault())->toBeFalse()
        ->and(Environment::Development->debugByDefault())->toBeTrue()
        ->and(Environment::Production->isProduction())->toBeTrue()
        ->and(Environment::Testing->isTesting())->toBeTrue()
        ->and(Environment::Development->isDevelopment())->toBeTrue();
});

it('monta o AppConfig tipado a partir da configuração', function (): void {
    $app = AppConfig::fromConfig(new Config(['app' => ['name' => 'Blog', 'env' => 'dev', 'timezone' => 'America/Sao_Paulo']]));

    expect($app->name)->toBe('Blog')
        ->and($app->environment)->toBe(Environment::Development)
        ->and($app->debug)->toBeTrue()
        ->and($app->timezone)->toBe('America/Sao_Paulo')
        ->and(AppConfig::fromConfig(new Config(['app' => ['env' => 'production', 'debug' => true]]))->debug)->toBeTrue()
        ->and(AppConfig::fromConfig(new Config())->environment)->toBe(Environment::Production);
});
