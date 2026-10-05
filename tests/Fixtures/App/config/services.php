<?php

declare(strict_types=1);

use Forja\Config\Env;

return [
    'secret' => Env::string('FIXTURE_SECRET'),
    'flag' => Env::bool('FIXTURE_FLAG'),
    'mail' => [
        'host' => 'smtp.forja.test',
        'port' => 587,
    ],
];
