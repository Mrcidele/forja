<?php

declare(strict_types=1);

namespace Forja\Console\Command;

use Forja\Database\Migrations\Migrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'migrate', description: 'Executa as migrations pendentes')]
final class MigrateCommand extends Command
{
    public function __construct(private readonly Migrator $migrator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ran = $this->migrator->migrate();

        if ($ran === []) {
            $output->writeln('<info>Nada para migrar.</info>');

            return self::SUCCESS;
        }

        foreach ($ran as $migration) {
            $output->writeln("<info>Migrada:</info> {$migration}");
        }

        return self::SUCCESS;
    }
}
