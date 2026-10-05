<?php

declare(strict_types=1);

use Forja\Config\Env;

return [
    'cookie' => 'forja_session',
    'lifetime' => 7200,
    'secure' => Env::bool('SESSION_SECURE', false),
    'same_site' => 'Lax',
];
