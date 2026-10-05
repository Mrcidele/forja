<?php

declare(strict_types=1);

namespace Forja\Http\Emitter;

use RuntimeException;

final class EmitterException extends RuntimeException
{
    public static function headersAlreadySent(): self
    {
        return new self('Não é possível emitir a resposta: os headers já foram enviados.');
    }

    public static function outputAlreadySent(): self
    {
        return new self('Não é possível emitir a resposta: já existe saída no buffer.');
    }
}
