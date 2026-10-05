<?php

declare(strict_types=1);

namespace Forja\View;

use LogicException;
use Stringable;

/**
 * Estado de uma renderização ($__env nos templates compilados): seções,
 * layout pai e helpers de escape e include.
 *
 * @internal
 */
final class RenderContext
{
    /** @var array<string, string> */
    private array $sections = [];

    /** @var list<string> */
    private array $sectionStack = [];

    private ?string $layout = null;

    public function __construct(
        private readonly Engine $engine,
    ) {
    }

    public function e(mixed $value): string
    {
        if ($value === null || is_bool($value)) {
            return $value === true ? '1' : '';
        }

        if (! is_scalar($value) && ! $value instanceof Stringable) {
            throw new LogicException(sprintf('Não é possível exibir um valor do tipo [%s] no template.', get_debug_type($value)));
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $variables variáveis do template atual
     * @param array<string, mixed> $data
     */
    public function include(array $variables, string $name, array $data = []): string
    {
        unset($variables['__env'], $variables['__path'], $variables['__data']);

        return $this->engine->renderWithContext($this, $name, [...$variables, ...$data]);
    }

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function startSection(string $name, ?string $content = null): void
    {
        if ($content !== null) {
            $this->sections[$name] ??= $this->e($content);

            return;
        }

        $this->sectionStack[] = $name;
        ob_start();
    }

    public function stopSection(): void
    {
        $name = array_pop($this->sectionStack) ?? throw new LogicException('@endsection sem @section correspondente.');
        $content = (string) ob_get_clean();

        // A seção definida pelo template mais específico (o filho) prevalece.
        $this->sections[$name] ??= $content;
    }

    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $this->e($default);
    }

    /**
     * @internal
     */
    public function pullLayout(): ?string
    {
        $layout = $this->layout;
        $this->layout = null;

        return $layout;
    }

    /**
     * @internal
     */
    public function openSections(): int
    {
        return count($this->sectionStack);
    }

    /**
     * Descarta seções abertas além de $keep (após erro de template).
     *
     * @internal
     */
    public function discardOpenSections(int $keep): void
    {
        $this->sectionStack = array_slice($this->sectionStack, 0, $keep);
    }
}
