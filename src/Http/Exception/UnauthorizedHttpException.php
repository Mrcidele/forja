<?php

declare(strict_types=1);

namespace Forja\Http\Exception;

use Throwable;

final class UnauthorizedHttpException extends HttpException
{
    /**
     * @param string|null $challenge valor do header WWW-Authenticate, como 'Bearer realm="api"'
     */
    public function __construct(?string $challenge = null, string $message = 'Autenticação necessária.', ?Throwable $previous = null)
    {
        parent::__construct(401, $message, $challenge !== null ? ['WWW-Authenticate' => $challenge] : [], $previous);
    }
}
