<?php

declare(strict_types=1);

use Forja\Runtime\FrankenPhpRunner;

// Ponto de entrada do worker mode do FrankenPHP (veja deploy/Caddyfile).
require __DIR__ . '/../vendor/autoload.php';

/** @var Forja\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

new FrankenPhpRunner($app, maxRequests: (int) ($_SERVER['MAX_REQUESTS'] ?? 1000))->run();
