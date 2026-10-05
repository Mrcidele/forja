<?php

declare(strict_types=1);

namespace Forja\Http\Exception;

use Throwable;

final class GoneHttpException extends HttpException
{
    public function __construct(string $message = 'O recurso não está mais disponível.', ?Throwable $previous = null)
    {
        parent::__construct(410, $message, [], $previous);
    }
}
