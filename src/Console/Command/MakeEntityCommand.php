<?php

declare(strict_types=1);

namespace Forja\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;

#[AsCommand(name: 'make:entity', description: 'Cria uma entidade mapeada por atributos (ORM)')]
final class MakeEntityCommand extends GeneratorCommand
{
    protected function stub(InputInterface $input): string
    {
        return 'entity';
    }

    protected function subNamespace(): string
    {
        return 'Entity';
    }

    protected function replacements(InputInterface $input, string $class): array
    {
        $table = self::snake($class);

        return ['table' => str_ends_with($table, 's') ? $table : $table . 's'];
    }
}
