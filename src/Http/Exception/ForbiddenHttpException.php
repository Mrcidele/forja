<?php

declare(strict_types=1);

namespace Forja\Http\Exception;

use Throwable;

final class ForbiddenHttpException extends HttpException
{
    public function __construct(string $message = 'Acesso negado.', ?Throwable $previous = null)
    {
        parent::__construct(403, $message, [], $previous);
    }
}
