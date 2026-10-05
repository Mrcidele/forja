<?php

declare(strict_types=1);

use Forja\Database\Migrations\Migrator;
use Forja\Foundation\Application;
use Psr\Http\Message\ResponseInterface;

/**
 * Aplicação com banco SQLite em memória já migrado.
 */
function app(): Application
{
    $app = Application::create(dirname(__DIR__));
    $app->container->get(Migrator::class)->migrate();

    return $app;
}

/**
 * @param array<string, string> $headers
 */
function request(Application $app, string $method, string $path, mixed $json = null, array $headers = []): ResponseInterface
{
    $body = $json === null ? null : json_encode($json);
    $request = new Nyholm\Psr7\ServerRequest($method, $path, $headers + ['Accept' => 'application/json'], $body, '1.1', ['REMOTE_ADDR' => '127.0.0.1']);

    if ($json !== null) {
        $request = $request->withHeader('Content-Type', 'application/json');
    }

    parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

    return $app->handle($request->withQueryParams($query));
}

function json(ResponseInterface $response): array
{
    return (array) json_decode((string) $response->getBody(), true);
}
