<?php

declare(strict_types=1);

namespace Forja\Console\Command;

use Forja\Config\ConfigLoader;
use Forja\Config\Env;
use Forja\Foundation\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'config:cache', description: 'Grava a configuração resolvida em um único arquivo')]
final class ConfigCacheCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('clear', null, InputOption::VALUE_NONE, 'Remove o cache em vez de gerá-lo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $this->app->cachePath('config.php');

        if (is_file($file)) {
            unlink($file);
        }

        if ($input->getOption('clear') === true) {
            $output->writeln('<info>Cache de configuração removido.</info>');

            return self::SUCCESS;
        }

        // Relê .env e config/*.php: o processo atual pode ter partido do cache antigo.
        Env::load($this->app->basePath());
        $loader = new ConfigLoader();
        $loader->dump($loader->load($this->app->configPath()), $file);
        $output->writeln('<info>Configuração em cache.</info>');

        return self::SUCCESS;
    }
}
