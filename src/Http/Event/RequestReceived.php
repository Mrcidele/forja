<?php

declare(strict_types=1);

namespace Forja\Http\Event;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Disparado quando a aplicação começa a atender uma requisição.
 */
final readonly class RequestReceived
{
    public function __construct(
        public ServerRequestInterface $request,
    ) {
    }
}
