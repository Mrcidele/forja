<?php

declare(strict_types=1);

namespace Forja\Http;

use InvalidArgumentException;
use JsonSerializable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Stringable;

/**
 * Atalhos para criar respostas comuns sobre as fábricas PSR-17.
 */
final readonly class ResponseFactory
{
    public const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    private ResponseFactoryInterface $responseFactory;

    private StreamFactoryInterface $streamFactory;

    public function __construct(?ResponseFactoryInterface $responseFactory = null, ?StreamFactoryInterface $streamFactory = null)
    {
        $default = new Psr17Factory();
        $this->responseFactory = $responseFactory ?? $default;
        $this->streamFactory = $streamFactory ?? $default;
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function create(int $status = 200, string $body = '', array $headers = []): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $body === '' ? $response : $response->withBody($this->streamFactory->createStream($body));
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function json(mixed $data, int $status = 200, array $headers = [], int $flags = self::JSON_FLAGS): ResponseInterface
    {
        return $this->create($status, json_encode($data, $flags | JSON_THROW_ON_ERROR), $headers + ['Content-Type' => 'application/json; charset=utf-8']);
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function html(string $html, int $status = 200, array $headers = []): ResponseInterface
    {
        return $this->create($status, $html, $headers + ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function text(string $text, int $status = 200, array $headers = []): ResponseInterface
    {
        return $this->create($status, $text, $headers + ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function redirect(string $url, int $status = 302): ResponseInterface
    {
        return $this->create($status, '', ['Location' => $url]);
    }

    public function noContent(): ResponseInterface
    {
        return $this->create(204);
    }

    /**
     * Converte o retorno de um controller em resposta: ResponseInterface passa
     * direto, null vira 204, strings viram HTML e arrays/JsonSerializable viram JSON.
     */
    public function fromValue(mixed $value): ResponseInterface
    {
        return match (true) {
            $value instanceof ResponseInterface => $value,
            $value === null => $this->noContent(),
            is_string($value), $value instanceof Stringable => $this->html((string) $value),
            is_array($value), $value instanceof JsonSerializable, is_scalar($value) => $this->json($value),
            default => throw new InvalidArgumentException(sprintf('Não é possível converter um valor do tipo [%s] em resposta.', get_debug_type($value))),
        };
    }
}
