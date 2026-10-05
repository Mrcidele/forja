<?php

declare(strict_types=1);

namespace Forja\Http\Exception;

use Throwable;

final class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = 'Recurso não encontrado.', ?Throwable $previous = null)
    {
        parent::__construct(404, $message, [], $previous);
    }
}
