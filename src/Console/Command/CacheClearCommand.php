<?php

declare(strict_types=1);

namespace Forja\Console\Command;

use FilesystemIterator;
use Forja\Foundation\Application;
use Psr\SimpleCache\CacheInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cache:clear', description: 'Limpa o cache da aplicação e os arquivos compilados')]
final class CacheClearCommand extends Command
{
    public function __construct(
        private readonly Application $app,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cache->clear();
        $removed = 0;
        $directory = $this->app->cachePath();

        if (is_dir($directory)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } elseif (unlink($file->getPathname())) {
                    $removed++;
                }
            }
        }

        $output->writeln(sprintf('<info>Cache limpo (%d arquivo(s) compilado(s) removido(s)).</info>', $removed));

        return self::SUCCESS;
    }
}
