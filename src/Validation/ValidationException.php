<?php

declare(strict_types=1);

namespace Forja\Validation;

use Forja\Http\Exception\UnprocessableEntityHttpException;

/**
 * Dados inválidos: vira resposta 422 com as mensagens por campo.
 */
final class ValidationException extends UnprocessableEntityHttpException
{
}
