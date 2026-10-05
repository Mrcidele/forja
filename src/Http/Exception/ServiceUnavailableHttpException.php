<?php

declare(strict_types=1);

namespace Forja\Http\Exception;

use Throwable;

final class ServiceUnavailableHttpException extends HttpException
{
    public function __construct(?int $retryAfter = null, string $message = 'Serviço temporariamente indisponível.', ?Throwable $previous = null)
    {
        parent::__construct(503, $message, $retryAfter !== null ? ['Retry-After' => (string) $retryAfter] : [], $previous);
    }
}
