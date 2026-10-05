<?php

declare(strict_types=1);

namespace Forja\Routing;

use Closure;
use Forja\Container\Container;
use RuntimeException;

/**
 * Monta as rotas da aplicação a partir das fontes configuradas: diretórios
 * de controllers (atributos) e arquivos de rotas que retornam uma closure
 * recebendo a RouteCollection.
 */
final readonly class RouteLoader
{
    /**
     * @param list<string> $controllerDirectories
     * @param list<string> $routeFiles
     */
    public function __construct(
        private Container $container,
        private array $controllerDirectories = [],
        private array $routeFiles = [],
        private AttributeRouteLoader $attributes = new AttributeRouteLoader(),
    ) {
    }

    /**
     * Router novo com todas as rotas (ignora o cache).
     */
    public function router(): Router
    {
        $router = new Router();
        $this->load($router->routes());

        return $router;
    }

    public function load(RouteCollection $routes): void
    {
        foreach ($this->controllerDirectories as $directory) {
            $this->attributes->loadDirectory($routes, $directory);
        }

        foreach ($this->routeFiles as $file) {
            $definition = require $file;

            if (! $definition instanceof Closure) {
                throw new RuntimeException(sprintf('O arquivo de rotas [%s] deve retornar uma closure que recebe a RouteCollection.', $file));
            }

            $this->container->call($definition, [RouteCollection::class => $routes]);
        }
    }
}
