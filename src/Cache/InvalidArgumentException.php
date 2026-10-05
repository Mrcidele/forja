<?php

declare(strict_types=1);

namespace Forja\Cache;

use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

final class InvalidArgumentException extends \InvalidArgumentException implements PsrInvalidArgumentException
{
    public static function invalidKey(string $key): self
    {
        return new self(sprintf('Chave de cache inválida: [%s]. Use uma string não vazia sem os caracteres {}()/\@:', $key));
    }
}
