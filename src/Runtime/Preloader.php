<?php

declare(strict_types=1);

namespace Forja\Runtime;

use Forja\Support\ClassFinder;
use RuntimeException;

/**
 * Gera o script de preload do OPcache (opcache.preload).
 *
 * O script carrega as classes pelo autoloader do Composer, o que garante que
 * classes-mãe e interfaces sejam vinculadas e fiquem em memória compartilhada
 * desde a inicialização do PHP.
 */
final readonly class Preloader
{
    public function __construct(
        private ClassFinder $finder = new ClassFinder(),
    ) {
    }

    /**
     * @param list<string> $directories diretórios com as classes a pré-carregar
     */
    public function generate(array $directories, string $autoloadPath): string
    {
        $classes = [];

        foreach ($directories as $directory) {
            $classes = [...$classes, ...$this->finder->find($directory)];
        }

        $classes = array_values(array_unique($classes));
        sort($classes);

        $list = implode('', array_map(static fn (string $class): string => '    ' . var_export($class, true) . ",\n", $classes));

        return <<<PHP
            <?php

            declare(strict_types=1);

            // Gerado por Forja\\Runtime\\Preloader. Configure no php.ini:
            // opcache.preload=<caminho deste arquivo>
            // opcache.preload_user=<usuário do PHP-FPM ou FrankenPHP>

            require_once {$this->export($autoloadPath)};

            \$classes = [
            {$list}];

            foreach (\$classes as \$class) {
                class_exists(\$class);
            }

            return count(\$classes);

            PHP;
    }

    /**
     * @param list<string> $directories
     */
    public function dump(array $directories, string $autoloadPath, string $file): void
    {
        $directory = dirname($file);

        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Não foi possível criar o diretório [%s].', $directory));
        }

        file_put_contents($file, $this->generate($directories, $autoloadPath));
    }

    private function export(string $value): string
    {
        return var_export($value, true);
    }
}
