<?php

declare(strict_types=1);

namespace Forja\Console\Command;

use Forja\Database\Migrations\Migrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'migrate:rollback', description: 'Desfaz o último lote de migrations', aliases: ['rollback'])]
final class MigrateRollbackCommand extends Command
{
    public function __construct(private readonly Migrator $migrator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('step', null, InputOption::VALUE_REQUIRED, 'Quantidade de lotes a desfazer', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $step = $input->getOption('step');
        $reverted = $this->migrator->rollback(is_numeric($step) ? max(1, (int) $step) : 1);

        if ($reverted === []) {
            $output->writeln('<info>Nada para desfazer.</info>');

            return self::SUCCESS;
        }

        foreach ($reverted as $migration) {
            $output->writeln("<comment>Desfeita:</comment> {$migration}");
        }

        return self::SUCCESS;
    }
}
