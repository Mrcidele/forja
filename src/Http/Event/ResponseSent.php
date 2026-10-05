<?php

declare(strict_types=1);

namespace Forja\Http\Event;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Disparado depois que a resposta foi emitida para o cliente.
 */
final readonly class ResponseSent
{
    public function __construct(
        public ServerRequestInterface $request,
        public ResponseInterface $response,
        public float $durationMs,
    ) {
    }
}
