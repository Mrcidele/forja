<?php

declare(strict_types=1);

namespace Forja\Console\Command;

use Forja\Routing\Route;
use Forja\Routing\Router;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'route:list', description: 'Lista as rotas registradas')]
final class RouteListCommand extends Command
{
    public function __construct(private readonly Router $router)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $routes = $this->router->compiled()->routes;
        usort($routes, static fn (Route $a, Route $b): int => [$a->path, $a->methods] <=> [$b->path, $b->methods]);

        $rows = array_map(static fn (Route $route): array => [
            implode('|', $route->methods),
            $route->path,
            $route->name ?? '',
            $route->handlerName(),
            implode(', ', array_map(static fn (string $class): string => substr((string) strrchr('\\' . $class, '\\'), 1), $route->middleware)),
        ], $routes);

        new Table($output)->setHeaders(['Método', 'Caminho', 'Nome', 'Ação', 'Middleware'])->setRows($rows)->render();
        $output->writeln(sprintf('<info>%d rota(s).</info>', count($rows)));

        return self::SUCCESS;
    }
}
