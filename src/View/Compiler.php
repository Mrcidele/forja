<?php

declare(strict_types=1);

namespace Forja\View;

use RuntimeException;

/**
 * Compila templates para PHP puro.
 *
 * Sintaxe: {{ expr }} (escapado), {!! expr !!} (cru), {{-- comentário --}},
 * @{{ ... }} (literal) e diretivas @if/@elseif/@else/@endif, @unless,
 * @isset, @foreach, @for, @while, @php, @include, @extends, @section,
 * @endsection, @yield e @csrf.
 */
final class Compiler
{
    private const string LITERAL_OPEN = "\x00FORJA_LITERAL\x00";

    public function compile(string $template): string
    {
        $template = preg_replace('/\{\{--.*?--\}\}/s', '', $template) ?? $template;
        $template = str_replace('@{{', self::LITERAL_OPEN, $template);

        // O PHP descarta a quebra de linha logo após a tag de fechamento; ela é duplicada para preservar o texto.
        $template = preg_replace_callback('/\{!!\s*(.+?)\s*!!\}(\r?\n)?/s', static fn (array $m): string => '<?php echo ' . $m[1] . '; ?>' . str_repeat($m[2] ?? '', 2), $template) ?? $template;
        $template = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}(\r?\n)?/s', static fn (array $m): string => '<?php echo $__env->e(' . $m[1] . '); ?>' . str_repeat($m[2] ?? '', 2), $template) ?? $template;

        $template = $this->compileDirectives($template);

        return str_replace(self::LITERAL_OPEN, '{{', $template);
    }

    private function compileDirectives(string $template): string
    {
        $result = '';
        $offset = 0;
        $length = strlen($template);

        while (preg_match('/(?<![\w@])@(\w+)/', $template, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            [$token, $position] = $match[0];
            $name = $match[1][0];
            $end = $position + strlen($token);
            $arguments = null;

            $cursor = $end;

            while ($cursor < $length && ($template[$cursor] === ' ' || $template[$cursor] === "\t")) {
                $cursor++;
            }

            if ($cursor < $length && $template[$cursor] === '(') {
                $close = $this->matchingParenthesis($template, $cursor);
                $arguments = substr($template, $cursor + 1, $close - $cursor - 1);
                $end = $close + 1;
            }

            $compiled = $this->directive($name, $arguments);

            if ($compiled === null) {
                $result .= substr($template, $offset, $position + strlen($token) - $offset);
                $offset = $position + strlen($token);

                continue;
            }

            $result .= substr($template, $offset, $position - $offset) . $compiled;
            $offset = $end;
        }

        return $result . substr($template, $offset);
    }

    private function directive(string $name, ?string $arguments): ?string
    {
        $args = $arguments ?? '';

        return match ($name) {
            'if' => "<?php if ({$args}): ?>",
            'elseif' => "<?php elseif ({$args}): ?>",
            'else' => '<?php else: ?>',
            'endif', 'endunless', 'endisset' => '<?php endif; ?>',
            'unless' => "<?php if (! ({$args})): ?>",
            'isset' => "<?php if (isset({$args})): ?>",
            'foreach' => "<?php foreach ({$args}): ?>",
            'endforeach' => '<?php endforeach; ?>',
            'for' => "<?php for ({$args}): ?>",
            'endfor' => '<?php endfor; ?>',
            'while' => "<?php while ({$args}): ?>",
            'endwhile' => '<?php endwhile; ?>',
            'php' => $arguments === null ? '<?php ' : "<?php {$args}; ?>",
            'endphp' => ' ?>',
            'include' => "<?php echo \$__env->include(get_defined_vars(), {$args}); ?>",
            'extends' => "<?php \$__env->extend({$args}); ?>",
            'section' => "<?php \$__env->startSection({$args}); ?>",
            'endsection' => '<?php $__env->stopSection(); ?>',
            'yield' => "<?php echo \$__env->yieldSection({$args}); ?>",
            'csrf' => '<input type="hidden" name="_token" value="<?php echo $__env->e($csrf_token ?? \'\'); ?>">',
            default => null,
        };
    }

    /**
     * Posição do ")" que fecha o "(" em $open, ignorando parênteses dentro de strings.
     */
    private function matchingParenthesis(string $template, int $open): int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($template);

        for ($i = $open; $i < $length; $i++) {
            $char = $template[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')' && --$depth === 0) {
                return $i;
            }
        }

        throw new RuntimeException('Parêntese não fechado em diretiva do template.');
    }
}
