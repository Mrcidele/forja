<?php

declare(strict_types=1);

namespace Forja\Console\Command;

use Forja\Foundation\Application;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base dos comandos make:*: gera arquivos a partir de stubs.
 *
 * Os stubs do framework ficam em stubs/; um arquivo de mesmo nome em
 * stubs/ na raiz da aplicação tem prioridade e permite personalizá-los.
 */
abstract class GeneratorCommand extends Command
{
    public function __construct(
        protected readonly Application $app,
    ) {
        parent::__construct();
    }

    /**
     * Nome do stub (sem extensão) para a entrada informada.
     */
    abstract protected function stub(InputInterface $input): string;

    /**
     * Subnamespace dentro do namespace da aplicação (ex.: "Http\Controllers").
     */
    abstract protected function subNamespace(): string;

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Nome da classe (aceita subpastas: Admin/UserController)');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Sobrescreve o arquivo se já existir');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $segments = array_values(array_filter(preg_split('#[/\\\\]+#', is_string($name) ? $name : '') ?: [], static fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            $output->writeln('<error>Informe um nome válido.</error>');

            return self::INVALID;
        }

        $class = $this->className(array_pop($segments));
        $namespace = rtrim($this->appNamespace() . $this->subNamespace() . ($segments === [] ? '' : '\\' . implode('\\', $segments)), '\\');
        $path = $this->app->basePath('app/' . str_replace('\\', '/', $this->subNamespace()) . '/' . ($segments === [] ? '' : implode('/', $segments) . '/') . $class . '.php');

        return $this->write($input, $output, $path, ['namespace' => $namespace, 'class' => $class, ...$this->replacements($input, $class)]);
    }

    /**
     * Ajusta o nome digitado (ex.: adiciona o sufixo "Controller").
     */
    protected function className(string $name): string
    {
        return ucfirst($name);
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(InputInterface $input, string $class): array
    {
        return [];
    }

    /**
     * @param array<string, string> $replacements
     */
    protected function write(InputInterface $input, OutputInterface $output, string $path, array $replacements): int
    {
        if (is_file($path) && $input->getOption('force') !== true) {
            $output->writeln(sprintf('<error>O arquivo [%s] já existe. Use --force para sobrescrever.</error>', $this->relative($path)));

            return self::FAILURE;
        }

        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Não foi possível criar o diretório [%s].', $directory));
        }

        file_put_contents($path, $this->render($this->stub($input), $replacements));
        $output->writeln(sprintf('<info>Criado:</info> %s', $this->relative($path)));

        return self::SUCCESS;
    }

    /**
     * @param array<string, string> $replacements
     */
    protected function render(string $stub, array $replacements): string
    {
        $custom = $this->app->basePath('stubs/' . $stub . '.stub');
        $path = is_file($custom) ? $custom : dirname(__DIR__, 3) . '/stubs/' . $stub . '.stub';
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Stub [%s] não encontrado.', $stub));
        }

        foreach ($replacements as $key => $value) {
            $contents = str_replace('{{ ' . $key . ' }}', $value, $contents);
        }

        return $contents;
    }

    protected function appNamespace(): string
    {
        return rtrim($this->app->config()->string('app.namespace', 'App\\'), '\\') . '\\';
    }

    protected static function snake(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    protected static function kebab(string $value): string
    {
        return str_replace('_', '-', self::snake($value));
    }

    private function relative(string $path): string
    {
        $base = $this->app->basePath() . '/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
