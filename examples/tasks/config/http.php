<?php

declare(strict_types=1);

use Forja\Http\Middleware\CorsMiddleware;
use Forja\Http\Middleware\CsrfMiddleware;
use Forja\Http\Middleware\ErrorHandlerMiddleware;
use Forja\Http\Middleware\SessionMiddleware;

return [
    // Middlewares globais, do mais externo ao mais interno.
    'middleware' => [
        CorsMiddleware::class,
        ErrorHandlerMiddleware::class,
        SessionMiddleware::class,
        CsrfMiddleware::class,
    ],

    // Caminhos que sempre recebem erros em JSON.
    'api_prefixes' => ['/api'],

    // APIs autenticadas por token não usam o token CSRF da sessão.
    'csrf_except' => ['/api/*'],

    'rate_limit' => [
        'max_attempts' => 120,
        'decay_seconds' => 60,
    ],
];
