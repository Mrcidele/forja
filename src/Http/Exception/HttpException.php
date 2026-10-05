<?php

declare(strict_types=1);

namespace Forja\Http\Exception;

use RuntimeException;
use Throwable;

/**
 * Exceção que carrega o status HTTP (e headers) da resposta que deve gerar.
 */
class HttpException extends RuntimeException
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        private readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
