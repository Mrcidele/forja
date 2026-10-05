<?php

declare(strict_types=1);

namespace Forja\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:controller', description: 'Cria um controller com rotas por atributos')]
final class MakeControllerCommand extends GeneratorCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('invokable', 'i', InputOption::VALUE_NONE, 'Cria um controller de ação única (__invoke)');
    }

    protected function stub(InputInterface $input): string
    {
        return $input->getOption('invokable') === true ? 'controller.invokable' : 'controller';
    }

    protected function subNamespace(): string
    {
        return 'Http\Controllers';
    }

    protected function className(string $name): string
    {
        $name = ucfirst($name);

        return str_ends_with($name, 'Controller') ? $name : $name . 'Controller';
    }

    protected function replacements(InputInterface $input, string $class): array
    {
        $resource = self::kebab(substr($class, 0, -strlen('Controller')));

        return ['route' => '/' . $resource, 'name' => str_replace('-', '_', $resource)];
    }
}
