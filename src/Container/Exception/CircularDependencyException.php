<?php

declare(strict_types=1);

namespace Forja\Container\Exception;

final class CircularDependencyException extends ContainerException
{
    /**
     * @param list<string> $chain
     */
    public static function forChain(array $chain): self
    {
        return new self(sprintf('Dependência circular detectada: %s.', implode(' -> ', $chain)));
    }
}
