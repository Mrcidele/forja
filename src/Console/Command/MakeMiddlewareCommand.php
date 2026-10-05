<?php

declare(strict_types=1);

namespace Forja\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;

#[AsCommand(name: 'make:middleware', description: 'Cria um middleware PSR-15')]
final class MakeMiddlewareCommand extends GeneratorCommand
{
    protected function stub(InputInterface $input): string
    {
        return 'middleware';
    }

    protected function subNamespace(): string
    {
        return 'Http\Middleware';
    }
}
