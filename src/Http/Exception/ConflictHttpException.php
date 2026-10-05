<?php

declare(strict_types=1);

namespace Forja\Http\Exception;

use Throwable;

final class ConflictHttpException extends HttpException
{
    public function __construct(string $message = 'Conflito com o estado atual do recurso.', ?Throwable $previous = null)
    {
        parent::__construct(409, $message, [], $previous);
    }
}
