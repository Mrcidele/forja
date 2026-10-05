<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Forja\Config\Env;

return [
    'name' => Env::string('APP_NAME', 'Forja'),
    'env' => Env::string('APP_ENV', 'production'),
    'debug' => Env::get('APP_DEBUG'),
    'url' => Env::string('APP_URL', 'http://localhost'),
    'timezone' => Env::string('APP_TIMEZONE', 'UTC'),
    'namespace' => 'App\\',
    'providers' => [
        AppServiceProvider::class,
    ],
];
