<?php

declare(strict_types=1);

namespace Forja\Http\Exception;

use Throwable;

final class TooManyRequestsHttpException extends HttpException
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(int $retryAfter, array $headers = [], string $message = 'Muitas requisições.', ?Throwable $previous = null)
    {
        parent::__construct(429, $message, ['Retry-After' => (string) $retryAfter] + $headers, $previous);
    }
}
