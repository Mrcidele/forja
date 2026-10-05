<?php

declare(strict_types=1);

namespace Forja\Console\Command;

use Forja\Database\Migrations\Migrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'migrate:reset', description: 'Desfaz todas as migrations')]
final class MigrateResetCommand extends Command
{
    public function __construct(private readonly Migrator $migrator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->migrator->reset() as $migration) {
            $output->writeln("<comment>Desfeita:</comment> {$migration}");
        }

        return self::SUCCESS;
    }
}
