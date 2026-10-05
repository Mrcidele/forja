<?php

declare(strict_types=1);

/*
| Configuração global do Pest. Os testes de Feature e Unit usam o TestCase
| padrão; helpers compartilhados entram aqui conforme os componentes surgem.
*/

/**
 * Copia a aplicação de exemplo dos testes para um diretório temporário, para
 * que caches e arquivos gerados não sujem o repositório.
 */
function copyFixtureApp(): string
{
    $target = sys_get_temp_dir() . '/forja-app-' . bin2hex(random_bytes(4));
    copyDirectory(__DIR__ . '/Fixtures/App', $target);

    return $target;
}

function copyDirectory(string $source, string $target): void
{
    mkdir($target, 0o777, true);

    foreach (new FilesystemIterator($source) as $item) {
        assert($item instanceof SplFileInfo);
        $destination = $target . '/' . $item->getFilename();

        $item->isDir() ? copyDirectory($item->getPathname(), $destination) : copy($item->getPathname(), $destination);
    }
}

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    foreach (new FilesystemIterator($directory) as $item) {
        assert($item instanceof SplFileInfo);
        $item->isDir() && ! $item->isLink() ? removeDirectory($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($directory);
}
