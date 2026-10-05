<?php

declare(strict_types=1);

namespace Forja\Http\Exception;

use Throwable;

final class BadRequestHttpException extends HttpException
{
    public function __construct(string $message = 'Requisição inválida.', ?Throwable $previous = null)
    {
        parent::__construct(400, $message, [], $previous);
    }
}
