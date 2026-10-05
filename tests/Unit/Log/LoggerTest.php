<?php

declare(strict_types=1);

use Forja\Log\Handler\StreamHandler;
use Forja\Log\Level;
use Forja\Log\Logger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    $this->stream = fopen('php://memory', 'w+b');
});

function logged($stream): string
{
    rewind($stream);

    return (string) stream_get_contents($stream);
}

it('implementa a PSR-3 e interpola o contexto', function (): void {
    $logger = new Logger('forja', [new StreamHandler($this->stream)]);

    $logger->info('Usuário {user} entrou às {when} (admin: {admin})', ['user' => 'ada', 'when' => new DateTimeImmutable('2026-10-05T12:00:00+00:00'), 'admin' => true]);

    expect($logger)->toBeInstanceOf(LoggerInterface::class)
        ->and(logged($this->stream))->toMatch('/^\[\d{4}-\d\d\-\d\dT[^\]]+\] forja\.INFO: Usuário ada entrou às 2026-10-05T12:00:00\+00:00 \(admin: true\) \{"user":"ada","when":"2026-10-05T12:00:00\+00:00","admin":true\}\n$/');
});

it('respeita o nível mínimo do handler', function (): void {
    $logger = new Logger('app', [new StreamHandler($this->stream, Level::Warning)]);

    $logger->debug('detalhe');
    $logger->info('informação');
    $logger->error('falha');

    expect(logged($this->stream))->not->toContain('detalhe')->not->toContain('informação')->toContain('app.ERROR: falha');
});

it('serializa exceções do contexto em uma única linha', function (): void {
    $logger = new Logger('app', [new StreamHandler($this->stream)]);

    $logger->critical("Falha\nem duas linhas", ['exception' => new RuntimeException('banco fora')]);

    expect(substr_count(logged($this->stream), "\n"))->toBe(1)
        ->and(logged($this->stream))->toContain('Falha\nem duas linhas')->toContain('"class":"RuntimeException"')->toContain('"message":"banco fora"');
});

it('grava em arquivo criando o diretório', function (): void {
    $file = sys_get_temp_dir() . '/forja-logs-' . bin2hex(random_bytes(4)) . '/app.log';
    new Logger('app', [new StreamHandler($file)])->warning('em disco');

    expect(file_get_contents($file))->toContain('app.WARNING: em disco');

    unlink($file);
    rmdir(dirname($file));
});

it('valida os níveis', function (): void {
    expect(Level::fromName('ERROR'))->toBe(Level::Error)
        ->and(Level::Warning->includes(Level::Error))->toBeTrue()
        ->and(Level::Warning->includes(Level::Info))->toBeFalse()
        ->and(fn () => new Logger()->log('barulho', 'x'))->toThrow(InvalidArgumentException::class);
});
