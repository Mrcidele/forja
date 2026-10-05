<?php

declare(strict_types=1);

use Forja\Config\Env;

$database = Env::string('DB_DATABASE', 'var/database.sqlite');

return [
    'default' => Env::string('DB_CONNECTION', 'sqlite'),

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => $database === ':memory:' || str_starts_with($database, '/') ? $database : dirname(__DIR__) . '/' . $database,
        ],
        'mysql' => [
            'driver' => 'mysql',
            'host' => Env::string('DB_HOST', '127.0.0.1'),
            'port' => Env::int('DB_PORT', 3306),
            'database' => $database,
            'username' => Env::string('DB_USERNAME'),
            'password' => Env::string('DB_PASSWORD'),
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => Env::string('DB_HOST', '127.0.0.1'),
            'port' => Env::int('DB_PORT', 5432),
            'database' => $database,
            'username' => Env::string('DB_USERNAME'),
            'password' => Env::string('DB_PASSWORD'),
        ],
    ],

    'migrations' => dirname(__DIR__) . '/database/migrations',
];
