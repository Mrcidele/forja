<?php

declare(strict_types=1);

namespace Forja\Routing;

use Forja\Routing\Exception\InvalidRouteException;
use RuntimeException;

/**
 * Grava e lê o mapa de rotas compilado como um arquivo PHP (aproveita o OPcache).
 */
final class RouteCache
{
    public static function dump(CompiledRoutes $compiled, string $file): void
    {
        foreach ($compiled->routes as $route) {
            if (! $route->isCacheable()) {
                throw new InvalidRouteException(sprintf('A rota [%s] usa uma closure e não pode ir para o cache; use um controller.', $route->path));
            }
        }

        $directory = dirname($file);

        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Não foi possível criar o diretório [%s].', $directory));
        }

        $code = "<?php\n\ndeclare(strict_types=1);\n\n// Gerado por " . self::class . ". Não edite.\n\nreturn " . var_export($compiled, true) . ";\n";
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (file_put_contents($temporary, $code) === false || ! rename($temporary, $file)) {
            throw new RuntimeException(sprintf('Não foi possível gravar o cache de rotas em [%s].', $file));
        }
    }

    public static function load(string $file): CompiledRoutes
    {
        $compiled = require $file;

        if (! $compiled instanceof CompiledRoutes) {
            throw new RuntimeException(sprintf('O arquivo [%s] não contém rotas compiladas.', $file));
        }

        return $compiled;
    }
}
