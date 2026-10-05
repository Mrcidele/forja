<?php

declare(strict_types=1);

namespace Forja\Container\Exception;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
    public static function unresolvableParameter(string $parameter, string $context): self
    {
        return new self(sprintf('Não foi possível resolver o parâmetro $%s de %s.', $parameter, $context));
    }

    public static function invalidCallable(): self
    {
        return new self('O valor informado não é um callable válido para o container.');
    }
}
