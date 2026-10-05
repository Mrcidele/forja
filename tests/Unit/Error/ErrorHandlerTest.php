<?php

declare(strict_types=1);

use Forja\Error\ErrorHandler;
use Forja\Error\ExceptionHandler;
use Forja\Error\FatalErrorException;
use Forja\Http\Emitter\EmitterInterface;
use Psr\Http\Message\ResponseInterface;

beforeEach(function (): void {
    $this->emitted = new ArrayObject();
    $this->emitter = new readonly class ($this->emitted) implements EmitterInterface {
        /** @param ArrayObject<int, ResponseInterface> $emitted */
        public function __construct(private ArrayObject $emitted)
        {
        }

        public function emit(ResponseInterface $response): void
        {
            $this->emitted[] = $response;
        }
    };
    $this->handler = new ErrorHandler(new ExceptionHandler(), $this->emitter);
});

it('converte erros do PHP em ErrorException respeitando o error_reporting', function (): void {
    $level = error_reporting(E_ALL & ~E_USER_NOTICE);

    try {
        expect($this->handler->handleError(E_USER_NOTICE, 'ignorado'))->toBeFalse()
            ->and(fn (): bool => $this->handler->handleError(E_USER_WARNING, 'aviso', 'arquivo.php', 10))
            ->toThrow(ErrorException::class, 'aviso');
    } finally {
        error_reporting($level);
    }
});

it('emite a resposta de exceções não capturadas', function (): void {
    $this->handler->handleException(new RuntimeException('falha'));

    expect($this->emitted)->toHaveCount(1)
        ->and($this->emitted[0]->getStatusCode())->toBe(500);
});

it('trata apenas erros fatais no shutdown', function (): void {
    $this->handler->handleFatalError(null);
    $this->handler->handleFatalError(['type' => E_WARNING, 'message' => 'aviso', 'file' => 'a.php', 'line' => 1]);

    expect($this->emitted)->toHaveCount(0);

    $this->handler->handleFatalError(['type' => E_ERROR, 'message' => 'Allowed memory size exhausted', 'file' => 'a.php', 'line' => 3]);

    expect($this->emitted)->toHaveCount(1)
        ->and($this->emitted[0]->getStatusCode())->toBe(500);
});

it('registra e remove os handlers globais', function (): void {
    $this->handler->register();
    $current = set_error_handler(static fn (): bool => true);
    restore_error_handler();
    $this->handler->unregister();

    expect($current)->toBeInstanceOf(Closure::class);
});

it('usa FatalErrorException para erros fatais', function (): void {
    expect(new FatalErrorException('x'))->toBeInstanceOf(ErrorException::class);
});
