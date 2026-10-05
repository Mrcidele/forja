<?php

declare(strict_types=1);

namespace Forja\Error;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

interface ExceptionHandlerInterface
{
    /**
     * Registra a exceção (log, monitoramento).
     */
    public function report(Throwable $exception): void;

    /**
     * Converte a exceção na resposta enviada ao cliente.
     */
    public function render(Throwable $exception, ServerRequestInterface $request): ResponseInterface;
}
