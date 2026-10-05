<?php

declare(strict_types=1);

namespace Forja\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Cria ServerRequestInterface a partir das superglobais ou de arrays equivalentes.
 */
final readonly class RequestFactory
{
    private ServerRequestCreator $creator;

    public function __construct(?Psr17Factory $factory = null)
    {
        $factory ??= new Psr17Factory();
        $this->creator = new ServerRequestCreator($factory, $factory, $factory, $factory);
    }

    public function fromGlobals(): ServerRequestInterface
    {
        return $this->creator->fromGlobals();
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, string|list<string>> $headers
     * @param array<string, string> $cookies
     * @param array<string, mixed> $query
     * @param array<string, mixed>|null $parsedBody
     * @param array<string, mixed> $files
     * @param StreamInterface|resource|string|null $body
     */
    public function fromArrays(
        array $server,
        array $headers = [],
        array $cookies = [],
        array $query = [],
        ?array $parsedBody = null,
        array $files = [],
        mixed $body = null,
    ): ServerRequestInterface {
        return $this->creator->fromArrays($server, $headers, $cookies, $query, $parsedBody, $files, $body);
    }
}
