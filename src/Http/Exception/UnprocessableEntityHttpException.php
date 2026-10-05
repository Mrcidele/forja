<?php

declare(strict_types=1);

namespace Forja\Http\Exception;

use Throwable;

/**
 * 422: a requisição é bem formada mas os dados não passaram na validação.
 */
class UnprocessableEntityHttpException extends HttpException
{
    /**
     * @param array<string, list<string>> $errors mensagens por campo
     */
    public function __construct(
        private readonly array $errors = [],
        string $message = 'Os dados enviados são inválidos.',
        ?Throwable $previous = null,
    ) {
        parent::__construct(422, $message, [], $previous);
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
