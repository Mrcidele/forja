<?php

declare(strict_types=1);

use Forja\Http\Emitter\EmitterException;
use Forja\Http\Emitter\SapiEmitter;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;

/**
 * @return array{0: SapiEmitter, 1: ArrayObject<int, array{string, bool, int}>}
 */
function recordingEmitter(int $chunkSize = 8192, bool $headersSent = false): array
{
    /** @var ArrayObject<int, array{string, bool, int}> $headers */
    $headers = new ArrayObject();

    $emitter = new SapiEmitter(
        max(1, $chunkSize),
        static function (string $line, bool $replace, int $code) use ($headers): void {
            $headers[] = [$line, $replace, $code];
        },
        static fn (): bool => $headersSent,
    );

    return [$emitter, $headers];
}

function captureOutput(Closure $callback): string
{
    ob_start();

    try {
        $callback();
    } finally {
        $output = (string) ob_get_clean();
    }

    return $output;
}

it('emite headers, status e corpo', function (): void {
    [$emitter, $headers] = recordingEmitter();
    $response = new Response(201, ['Content-Type' => 'text/plain', 'X-Id' => ['1', '2']], 'criado');

    $output = captureOutput(static fn () => $emitter->emit($response));

    expect($output)->toBe('criado')
        ->and($headers->getArrayCopy())->toBe([
            ['Content-Type: text/plain', true, 201],
            ['X-Id: 1', true, 201],
            ['X-Id: 2', false, 201],
            ['HTTP/1.1 201 Created', true, 201],
        ]);
});

it('nunca substitui headers Set-Cookie', function (): void {
    [$emitter, $headers] = recordingEmitter();
    $response = new Response(200, ['Set-Cookie' => ['a=1', 'b=2']]);

    captureOutput(static fn () => $emitter->emit($response));

    expect($headers[0])->toBe(['Set-Cookie: a=1', false, 200])
        ->and($headers[1])->toBe(['Set-Cookie: b=2', false, 200]);
});

it('emite o status depois dos headers para preservar redirecionamentos', function (): void {
    [$emitter, $headers] = recordingEmitter();

    captureOutput(static fn () => $emitter->emit(new Response(301, ['Location' => '/novo'])));

    expect($headers->getArrayCopy())->toBe([
        ['Location: /novo', true, 301],
        ['HTTP/1.1 301 Moved Permanently', true, 301],
    ]);
});

it('lê o corpo em blocos a partir do início do stream', function (): void {
    $stream = Stream::create(str_repeat('x', 10));
    $stream->seek(5);
    [$emitter] = recordingEmitter(chunkSize: 3);

    $output = captureOutput(static fn () => $emitter->emit(new Response(200, [], $stream)));

    expect($output)->toBe(str_repeat('x', 10));
});

it('não emite corpo para status sem conteúdo', function (int $status): void {
    [$emitter] = recordingEmitter();

    $output = captureOutput(static fn () => $emitter->emit(new Response($status, [], 'ignorado')));

    expect($output)->toBe('');
})->with([100, 204, 304]);

it('falha quando os headers já foram enviados', function (): void {
    [$emitter] = recordingEmitter(headersSent: true);

    $emitter->emit(new Response());
})->throws(EmitterException::class, 'headers já foram enviados');

it('falha quando já existe saída no buffer', function (): void {
    [$emitter] = recordingEmitter();

    ob_start();
    echo 'saída prévia';

    try {
        $emitter->emit(new Response());
    } finally {
        ob_end_clean();
    }
})->throws(EmitterException::class, 'já existe saída');

it('rejeita tamanho de bloco inválido', function (): void {
    new SapiEmitter(0);
})->throws(InvalidArgumentException::class);
