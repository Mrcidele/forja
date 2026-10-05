<?php

declare(strict_types=1);

namespace Forja\Http\Exception;

use Throwable;

final class MethodNotAllowedHttpException extends HttpException
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(
        private readonly array $allowedMethods,
        string $message = 'Método não permitido.',
        ?Throwable $previous = null,
    ) {
        parent::__construct(405, $message, ['Allow' => implode(', ', $allowedMethods)], $previous);
    }

    /**
     * @return list<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
