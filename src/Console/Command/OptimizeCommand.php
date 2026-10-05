<?php

declare(strict_types=1);

namespace Forja\Console\Command;

use Forja\Config\Config;
use Forja\Container\ContainerCompiler;
use Forja\Foundation\Application;
use Forja\Routing\Route;
use Forja\Routing\RouteCache;
use Forja\Routing\RouteLoader;
use Forja\Runtime\Preloader;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'optimize', description: 'Gera os caches de produção: configuração, rotas, container e preload do OPcache')]
final class OptimizeCommand extends Command
{
    public function __construct(
        private readonly Application $app,
        private readonly RouteLoader $loader,
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        new ConfigCacheCommand($this->app)->run(new ArrayInput([]), $output);

        $router = $this->loader->router();
        RouteCache::dump($router->compiled(), $this->app->cachePath('routes.php'));
        $output->writeln('<info>Rotas em cache.</info>');

        new ContainerCompiler()->dump($this->compilableClasses($router->compiled()->routes), $this->app->cachePath('container.php'));
        $output->writeln('<info>Container compilado.</info>');

        new Preloader()->dump(
            [dirname((string) new ReflectionClass(Application::class)->getFileName(), 2), $this->app->basePath('app')],
            $this->autoloadPath(),
            $this->app->cachePath('preload.php'),
        );
        $output->writeln(sprintf('<info>Script de preload do OPcache gerado em %s.</info>', $this->app->cachePath('preload.php')));

        return self::SUCCESS;
    }

    private function autoloadPath(): string
    {
        $application = $this->app->basePath('vendor/autoload.php');

        return is_file($application) ? $application : dirname((string) new ReflectionClass(Application::class)->getFileName(), 3) . '/vendor/autoload.php';
    }

    /**
     * Classes construídas por autowiring em uma requisição típica: bindings,
     * controllers e middlewares.
     *
     * @param list<Route> $routes
     *
     * @return list<string>
     */
    private function compilableClasses(array $routes): array
    {
        $classes = array_values(array_filter($this->app->container->bindings(), is_string(...)));

        foreach ($routes as $route) {
            $handler = $route->handler;

            if (is_string($handler)) {
                $classes[] = $handler;
            } elseif (is_array($handler)) {
                $classes[] = $handler[0];
            }

            $classes = [...$classes, ...$route->middleware];
        }

        foreach ($this->config->array('http.middleware', []) as $middleware) {
            if (is_string($middleware)) {
                $classes[] = $middleware;
            }
        }

        return array_values(array_unique([...$classes, ...$this->app->container->autowiredClasses()]));
    }
}
