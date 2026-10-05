<?php

declare(strict_types=1);

namespace Forja\View;

use RuntimeException;
use Throwable;

/**
 * Engine de templates: localiza a view ("users.show" -> users/show.forja.php),
 * compila para PHP com cache em disco (recompila quando o arquivo muda) e
 * renderiza com layouts, seções e includes.
 */
final class Engine
{
    /** @var list<string> */
    private readonly array $paths;

    /** @var array<string, mixed> */
    private array $shared = [];

    /**
     * @param string|list<string> $paths diretórios de views, em ordem de prioridade
     */
    public function __construct(
        string|array $paths,
        private readonly string $cachePath,
        private readonly string $extension = '.forja.php',
        private readonly Compiler $compiler = new Compiler(),
    ) {
        $this->paths = array_map(static fn (string $path): string => rtrim($path, '/'), is_string($paths) ? [$paths] : $paths);
    }

    /**
     * Disponibiliza um valor para todas as views.
     */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $name, array $data = []): string
    {
        return $this->renderWithContext(new RenderContext($this), $name, $data);
    }

    public function exists(string $name): bool
    {
        return $this->find($name) !== null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @internal usado por includes e layouts
     */
    public function renderWithContext(RenderContext $context, string $name, array $data): string
    {
        $data = [...$this->shared, ...$data];
        $output = $this->evaluate($this->compiled($name), $data, $context);

        while (($layout = $context->pullLayout()) !== null) {
            $output = $this->evaluate($this->compiled($layout), $data, $context);
        }

        return $output;
    }

    public function compiledPath(string $name): string
    {
        $source = $this->find($name) ?? throw new ViewNotFoundException(sprintf('View [%s] não encontrada em: %s.', $name, implode(', ', $this->paths)));

        return $this->cachePath . '/' . hash('xxh128', $source) . '.php';
    }

    private function compiled(string $name): string
    {
        $source = $this->find($name) ?? throw new ViewNotFoundException(sprintf('View [%s] não encontrada em: %s.', $name, implode(', ', $this->paths)));
        $compiled = $this->compiledPath($name);

        if (! is_file($compiled) || filemtime($compiled) < filemtime($source)) {
            $this->write($compiled, "<?php /* {$source} */ ?>\n" . $this->compiler->compile((string) file_get_contents($source)));
        }

        return $compiled;
    }

    private function find(string $name): ?string
    {
        $relative = str_replace('.', '/', $name) . $this->extension;

        foreach ($this->paths as $path) {
            if (is_file($path . '/' . $relative)) {
                return $path . '/' . $relative;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $__data
     */
    private function evaluate(string $__path, array $__data, RenderContext $__env): string
    {
        $level = ob_get_level();
        $sections = $__env->openSections();
        ob_start();

        try {
            (static function () use ($__path, $__data, $__env): void {
                extract($__data, EXTR_SKIP);

                require $__path;
            })();
        } catch (Throwable $exception) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $exception;
        }

        if ($__env->openSections() > $sections) {
            $__env->discardOpenSections($sections);

            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw new RuntimeException(sprintf('Seção não fechada (falta @endsection) em [%s].', $__path));
        }

        return (string) ob_get_clean();
    }

    private function write(string $file, string $contents): void
    {
        $directory = dirname($file);

        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Não foi possível criar o diretório de cache de views [%s].', $directory));
        }

        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        file_put_contents($temporary, $contents);
        rename($temporary, $file);
    }
}
