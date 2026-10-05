<?php

declare(strict_types=1);

use Forja\Cache\ArrayCache;
use Forja\Cache\FileCache;
use Forja\Cache\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

$directory = sys_get_temp_dir() . '/forja-cache-' . bin2hex(random_bytes(4));

afterAll(function () use ($directory): void {
    foreach (glob($directory . '/*/*') ?: [] as $file) {
        unlink($file);
    }

    foreach (glob($directory . '/*') ?: [] as $subdirectory) {
        rmdir($subdirectory);
    }

    if (is_dir($directory)) {
        rmdir($directory);
    }
});

dataset('caches', ['array', 'arquivo']);

beforeEach(function () use ($directory): void {
    $this->now = 100;
    $clock = fn (): int => $this->now;
    $this->makeCache = static fn (string $type): CacheInterface => $type === 'array'
        ? new ArrayCache($clock)
        : new FileCache($directory . '/' . bin2hex(random_bytes(4)), $clock);
});

it('grava, lê e remove valores', function (string $type): void {
    $cache = ($this->makeCache)($type);

    expect($cache->get('chave', 'padrão'))->toBe('padrão')
        ->and($cache->set('chave', ['a' => 1]))->toBeTrue()
        ->and($cache->get('chave'))->toBe(['a' => 1])
        ->and($cache->has('chave'))->toBeTrue();

    $cache->set('falso', false);

    expect($cache->get('falso', 'padrão'))->toBeFalse();

    $cache->delete('chave');

    expect($cache->has('chave'))->toBeFalse();
})->with('caches');

it('expira valores pelo TTL em segundos ou DateInterval', function (string $type): void {
    $cache = ($this->makeCache)($type);
    $cache->set('segundos', 1, 10);
    $cache->set('intervalo', 2, new DateInterval('PT1M'));
    $cache->set('eterno', 3);

    $this->now = 110;

    expect($cache->has('segundos'))->toBeFalse()
        ->and($cache->get('intervalo'))->toBe(2)
        ->and($cache->get('eterno'))->toBe(3);

    $cache->set('eterno', 4, 0);

    expect($cache->has('eterno'))->toBeFalse();
})->with('caches');

it('opera em lote e limpa tudo', function (string $type): void {
    $cache = ($this->makeCache)($type);
    $cache->setMultiple(['a' => 1, 'b' => 2]);

    expect($cache->getMultiple(['a', 'b', 'c'], 0))->toBe(['a' => 1, 'b' => 2, 'c' => 0]);

    $cache->deleteMultiple(['a']);

    expect($cache->has('a'))->toBeFalse();

    $cache->clear();

    expect($cache->has('b'))->toBeFalse();
})->with('caches');

it('rejeita chaves inválidas', function (string $type, string $key): void {
    $cache = ($this->makeCache)($type);

    expect(fn (): mixed => $cache->get($key))->toThrow(InvalidArgumentException::class)
        ->and(new InvalidArgumentException())->toBeInstanceOf(PsrInvalidArgumentException::class);
})->with('caches')->with(['', 'a/b', 'a:b', '{x}', 'a@b']);
