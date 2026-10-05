<?php

declare(strict_types=1);

namespace Forja\Console\Command;

use Forja\Database\Migrations\Migrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'migrate:status', description: 'Mostra quais migrations já foram executadas')]
final class MigrateStatusCommand extends Command
{
    public function __construct(private readonly Migrator $migrator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = array_map(static fn (array $status): array => [
            $status['ran'] ? '<info>Sim</info>' : '<comment>Não</comment>',
            $status['migration'],
            $status['batch'] ?? '-',
        ], $this->migrator->status());

        new Table($output)->setHeaders(['Executada', 'Migration', 'Lote'])->setRows($rows)->render();

        return self::SUCCESS;
    }
}
