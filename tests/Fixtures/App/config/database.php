<?php

declare(strict_types=1);

return [
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
    ],
    'migrations' => __DIR__ . '/../database/migrations',
];
