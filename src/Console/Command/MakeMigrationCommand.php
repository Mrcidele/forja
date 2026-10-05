<?php

declare(strict_types=1);

namespace Forja\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make:migration', description: 'Cria uma migration versionada')]
final class MakeMigrationCommand extends GeneratorCommand
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Nome em snake_case, ex.: create_posts_table');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Sobrescreve o arquivo se já existir');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $argument = $input->getArgument('name');
        $name = self::snake(preg_replace('/\W+/', '_', is_string($argument) ? trim($argument) : '') ?? '');

        if ($name === '') {
            $output->writeln('<error>Informe um nome válido.</error>');

            return self::INVALID;
        }

        $directory = $this->app->config()->string('database.migrations', $this->app->basePath('database/migrations'));
        $path = rtrim($directory, '/') . '/' . gmdate('Y_m_d_His') . '_' . $name . '.php';

        return $this->write($input, $output, $path, ['table' => $this->table($name)]);
    }

    protected function stub(InputInterface $input): string
    {
        $name = $input->getArgument('name');

        return is_string($name) && preg_match('/^create_\w+_table$/', self::snake($name)) === 1 ? 'migration.create' : 'migration';
    }

    protected function subNamespace(): string
    {
        return '';
    }

    private function table(string $name): string
    {
        if (preg_match('/^create_(\w+)_table$/', $name, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/_(?:to|from|in|on)_(\w+?)(?:_table)?$/', $name, $matches) === 1) {
            return $matches[1];
        }

        return 'tabela';
    }
}
