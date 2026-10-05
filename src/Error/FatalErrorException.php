<?php

declare(strict_types=1);

namespace Forja\Error;

use ErrorException;

/**
 * Erro fatal do PHP (E_ERROR, E_PARSE...) capturado no shutdown.
 */
final class FatalErrorException extends ErrorException
{
}
