<?php

declare(strict_types=1);

namespace Forja\Error;

use Forja\Http\Exception\HttpException;
use Forja\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Converte exceções em respostas: HttpException usa o próprio status e
 * mensagem; qualquer outra vira 500 com mensagem genérica.
 */
class ExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        protected readonly ResponseFactory $responses = new ResponseFactory(),
    ) {
    }

    public function report(Throwable $exception): void
    {
    }

    public function render(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        if ($exception instanceof HttpException) {
            return $this->responses->text($exception->getMessage(), $exception->getStatusCode(), $exception->getHeaders());
        }

        return $this->responses->text('Erro interno do servidor.', 500);
    }
}
