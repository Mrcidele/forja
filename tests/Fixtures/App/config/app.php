<?php

declare(strict_types=1);

use Forja\Config\Env;
use Forja\Tests\Fixtures\App\FixtureProvider;

return [
    'name' => Env::string('FIXTURE_APP_NAME', 'Forja'),
    'env' => Env::string('FIXTURE_APP_ENV', 'production'),
    'debug' => Env::get('FIXTURE_APP_DEBUG'),
    'url' => 'http://forja.test',
    'timezone' => 'America/Sao_Paulo',
    'providers' => [FixtureProvider::class],
];
