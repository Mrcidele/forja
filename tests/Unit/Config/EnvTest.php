<?php

declare(strict_types=1);

use Forja\Config\Env;

afterEach(function (): void {
    foreach (['FORJA_ENV_TEST', 'FORJA_ENV_EXISTING', 'FORJA_ENV_FILE', 'FORJA_ENV_QUOTED'] as $key) {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }
});

it('converte valores especiais', function (string $raw, mixed $expected): void {
    $_ENV['FORJA_ENV_TEST'] = $raw;

    expect(Env::get('FORJA_ENV_TEST'))->toBe($expected);
})->with([
    ['true', true],
    ['(false)', false],
    ['null', null],
    ['empty', ''],
    ['texto', 'texto'],
]);

it('usa o padrão para variáveis ausentes e oferece getters tipados', function (): void {
    $_ENV['FORJA_ENV_TEST'] = '8080';

    expect(Env::get('FORJA_ENV_INEXISTENTE', 'padrão'))->toBe('padrão')
        ->and(Env::int('FORJA_ENV_TEST'))->toBe(8080)
        ->and(Env::string('FORJA_ENV_TEST'))->toBe('8080')
        ->and(Env::bool('FORJA_ENV_INEXISTENTE', true))->toBeTrue()
        ->and(Env::int('FORJA_ENV_INEXISTENTE', 3))->toBe(3);
});

it('carrega o .env sem sobrescrever variáveis reais', function (): void {
    $directory = sys_get_temp_dir() . '/forja-env-' . bin2hex(random_bytes(4));
    mkdir($directory);
    file_put_contents($directory . '/.env', "FORJA_ENV_FILE=do-arquivo\nFORJA_ENV_EXISTING=do-arquivo\nFORJA_ENV_QUOTED=\"com espaço\"\n");
    $_ENV['FORJA_ENV_EXISTING'] = 'real';

    try {
        Env::load($directory);
    } finally {
        unlink($directory . '/.env');
        rmdir($directory);
    }

    expect(Env::get('FORJA_ENV_FILE'))->toBe('do-arquivo')
        ->and(Env::get('FORJA_ENV_EXISTING'))->toBe('real')
        ->and(Env::get('FORJA_ENV_QUOTED'))->toBe('com espaço');
});

it('ignora a ausência do arquivo .env', function (): void {
    Env::load('/diretorio/inexistente');

    expect(true)->toBeTrue();
});
