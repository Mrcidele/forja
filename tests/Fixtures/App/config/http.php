<?php

declare(strict_types=1);

use Forja\Http\Middleware\ErrorHandlerMiddleware;

return [
    'middleware' => [ErrorHandlerMiddleware::class],
    'api_prefixes' => ['/api'],
];
