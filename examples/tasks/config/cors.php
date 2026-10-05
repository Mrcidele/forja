<?php

declare(strict_types=1);

return [
    'allowed_origins' => ['*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'allow_credentials' => false,
    'max_age' => 600,
];
