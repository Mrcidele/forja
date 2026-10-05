<?php

declare(strict_types=1);

namespace Forja\Console\Command;

use Forja\Foundation\Application;
use Forja\Routing\RouteCache;
use Forja\Routing\RouteLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'route:cache', description: 'Compila o mapa de rotas para produção')]
final class RouteCacheCommand extends Command
{
    public function __construct(
        private readonly Application $app,
        private readonly RouteLoader $loader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('clear', null, InputOption::VALUE_NONE, 'Remove o cache em vez de gerá-lo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $this->app->cachePath('routes.php');

        if ($input->getOption('clear') === true) {
            if (is_file($file)) {
                unlink($file);
            }

            $output->writeln('<info>Cache de rotas removido.</info>');

            return self::SUCCESS;
        }

        RouteCache::dump($this->loader->router()->compiled(), $file);
        $output->writeln('<info>Rotas em cache.</info>');

        return self::SUCCESS;
    }
}
