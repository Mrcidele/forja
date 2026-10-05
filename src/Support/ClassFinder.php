<?php

declare(strict_types=1);

namespace Forja\Support;

use FilesystemIterator;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Encontra as classes declaradas nos arquivos PHP de um diretório lendo os
 * tokens, sem precisar incluir os arquivos.
 */
final class ClassFinder
{
    /**
     * @return list<class-string>
     */
    public function find(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);
        $classes = [];

        foreach ($files as $file) {
            foreach ($this->classesIn((string) file_get_contents($file)) as $class) {
                if (! class_exists($class)) {
                    require_once $file;
                }

                if (class_exists($class, false)) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    /**
     * @return list<string>
     */
    public function classesIn(string $code): array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($code),
            static fn (PhpToken $token): bool => ! $token->isIgnorable(),
        ));

        $namespace = '';
        $classes = [];

        foreach ($tokens as $index => $token) {
            if ($token->is(T_NAMESPACE)) {
                $next = $tokens[$index + 1] ?? null;
                $namespace = $next !== null && $next->is([T_STRING, T_NAME_QUALIFIED]) ? $next->text . '\\' : '';

                continue;
            }

            if (! $token->is(T_CLASS)) {
                continue;
            }

            $previous = $tokens[$index - 1] ?? null;
            $next = $tokens[$index + 1] ?? null;

            // Ignora "Foo::class" e classes anônimas ("new class").
            if ($previous !== null && $previous->is([T_DOUBLE_COLON, T_NEW])) {
                continue;
            }

            if ($next !== null && $next->is(T_STRING)) {
                $classes[] = $namespace . $next->text;
            }
        }

        return $classes;
    }
}
