<?php

declare(strict_types=1);

use Forja\Error\ExceptionHandler;
use Forja\Http\CallableHandler;
use Forja\Http\Exception\MethodNotAllowedHttpException;
use Forja\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

function failingHandler(Closure $fail): CallableHandler
{
    return new CallableHandler(static function () use ($fail): Response {
        $fail();

        return new Response();
    });
}

it('converte HttpException em resposta com status e headers', function (): void {
    $middleware = new ErrorHandlerMiddleware(new ExceptionHandler());

    $response = $middleware->process(new ServerRequest('PUT', '/'), failingHandler(static fn () => throw new MethodNotAllowedHttpException(['GET'])));

    expect($response->getStatusCode())->toBe(405)
        ->and($response->getHeaderLine('Allow'))->toBe('GET')
        ->and((string) $response->getBody())->toContain('Método não permitido.');
});

it('esconde detalhes de exceções genéricas', function (): void {
    $middleware = new ErrorHandlerMiddleware(new ExceptionHandler());

    $response = $middleware->process(new ServerRequest('GET', '/'), failingHandler(static fn () => throw new RuntimeException('segredo')));

    expect($response->getStatusCode())->toBe(500)
        ->and((string) $response->getBody())->not->toContain('segredo');
});

it('converte erros do PHP em exceções e relata cada uma', function (): void {
    $reported = new ArrayObject();
    $handler = new class ($reported) extends ExceptionHandler {
        /** @param ArrayObject<int, Throwable> $reported */
        public function __construct(private readonly ArrayObject $reported)
        {
            parent::__construct();
        }

        public function report(Throwable $exception): void
        {
            $this->reported[] = $exception;
        }
    };

    // O PHPUnit reduz o error_reporting durante os testes; o middleware respeita esse nível.
    $level = error_reporting(E_ALL);

    try {
        $response = new ErrorHandlerMiddleware($handler)->process(
            new ServerRequest('GET', '/'),
            failingHandler(static fn (): true => trigger_error('aviso', E_USER_WARNING)),
        );
    } finally {
        error_reporting($level);
    }

    expect($response->getStatusCode())->toBe(500)
        ->and($reported[0])->toBeInstanceOf(ErrorException::class)
        ->and($reported[0]->getMessage())->toBe('aviso');
});

it('restaura o handler de erros anterior', function (): void {
    $previous = set_error_handler(static fn (): bool => true);
    restore_error_handler();

    new ErrorHandlerMiddleware(new ExceptionHandler())->process(new ServerRequest('GET', '/'), failingHandler(static fn (): null => null));

    $current = set_error_handler(static fn (): bool => true);
    restore_error_handler();

    expect($current)->toBe($previous);
});
