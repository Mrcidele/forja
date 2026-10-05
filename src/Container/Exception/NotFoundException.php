<?php

declare(strict_types=1);

namespace Forja\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

final class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Nenhuma entrada ou classe encontrada para [%s].', $id));
    }

    public static function notInstantiable(string $class): self
    {
        return new self(sprintf('A classe [%s] não pode ser instanciada (é abstrata ou tem construtor privado) e não há binding para ela.', $class));
    }
}
