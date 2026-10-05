<?php

declare(strict_types=1);

namespace Forja\Http\Emitter;

use Psr\Http\Message\ResponseInterface;

interface EmitterInterface
{
    /**
     * Envia status, headers e corpo da resposta para o cliente.
     *
     * @throws EmitterException quando já houve saída antes da emissão
     */
    public function emit(ResponseInterface $response): void;
}
