<?php

declare(strict_types=1);

namespace Forja\Http\Middleware;

use ErrorException;
use Forja\Error\ExceptionHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Captura qualquer exceção (e erros do PHP, convertidos em ErrorException)
 * lançada pelas camadas internas e a transforma em resposta.
 *
 * Deve ser um dos primeiros middlewares globais.
 */
final readonly class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ExceptionHandlerInterface $handler,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            $this->handler->report($exception);

            return $this->handler->render($exception, $request);
        } finally {
            restore_error_handler();
        }
    }
}
