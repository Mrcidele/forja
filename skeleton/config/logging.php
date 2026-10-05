<?php

declare(strict_types=1);

use Forja\Config\Env;

return [
    'channel' => 'app',
    'path' => dirname(__DIR__) . '/var/logs/app.log',
    'level' => Env::string('LOG_LEVEL', 'debug'),
];
