<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

/** @var Forja\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$app->run();
