<?php

declare(strict_types=1);

namespace Forja\Config;

use RuntimeException;

/**
 * Lê os arquivos config/*.php (cada um retorna um array, exposto pelo nome
 * do arquivo) e grava/lê o cache com a configuração já resolvida.
 */
final class ConfigLoader
{
    /**
     * @return array<string, mixed>
     */
    public function load(string $directory): array
    {
        $items = [];
        $files = glob(rtrim($directory, '/') . '/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $values = require $file;

            if (! is_array($values)) {
                throw new RuntimeException(sprintf('O arquivo de configuração [%s] deve retornar um array.', $file));
            }

            $items[basename($file, '.php')] = $values;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $items
     */
    public function dump(array $items, string $file): void
    {
        $directory = dirname($file);

        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Não foi possível criar o diretório [%s].', $directory));
        }

        $code = "<?php\n\ndeclare(strict_types=1);\n\n// Gerado por " . self::class . ". Não edite.\n\nreturn " . var_export($items, true) . ";\n";
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (file_put_contents($temporary, $code) === false || ! rename($temporary, $file)) {
            throw new RuntimeException(sprintf('Não foi possível gravar o cache de configuração em [%s].', $file));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function loadCached(string $file): array
    {
        $items = require $file;

        if (! is_array($items)) {
            throw new RuntimeException(sprintf('O arquivo [%s] não contém configuração em cache.', $file));
        }

        /** @var array<string, mixed> $items */
        return $items;
    }
}
