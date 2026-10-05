<?php

declare(strict_types=1);

use Forja\Foundation\Application;
use Psr\Http\Message\ResponseInterface;

function app(): Application
{
    return Application::create(dirname(__DIR__));
}

function get(string $path, array $headers = []): ResponseInterface
{
    return app()->handle(new Nyholm\Psr7\ServerRequest('GET', $path, $headers));
}
