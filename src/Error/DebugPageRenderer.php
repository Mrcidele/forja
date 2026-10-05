<?php

declare(strict_types=1);

namespace Forja\Error;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Página HTML de depuração: exceção, trecho do código, stack trace (com as
 * exceções encadeadas) e dados da requisição. Use apenas em desenvolvimento.
 */
final readonly class DebugPageRenderer
{
    public function __construct(
        private int $contextLines = 7,
    ) {
    }

    public function render(Throwable $exception, ServerRequestInterface $request, int $status): string
    {
        $sections = '';

        for ($current = $exception; $current instanceof \Throwable; $current = $current->getPrevious()) {
            $sections .= $this->exceptionSection($current, $current === $exception);
        }

        return sprintf(
            <<<'HTML'
                <!doctype html>
                <html lang="pt-BR">
                <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>%s</title>
                <style>%s</style>
                </head>
                <body>
                <header><span class="status">%d</span> <span class="method">%s</span> %s</header>
                %s
                <section><h2>Requisição</h2>%s</section>
                </body>
                </html>
                HTML,
            $this->e($exception::class . ': ' . $exception->getMessage()),
            self::STYLE,
            $status,
            $this->e($request->getMethod()),
            $this->e((string) $request->getUri()),
            $sections,
            $this->requestTable($request),
        );
    }

    private function exceptionSection(Throwable $exception, bool $main): string
    {
        $frames = '';

        foreach ($exception->getTrace() as $index => $frame) {
            $call = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'] . '()';
            $location = isset($frame['file']) ? $frame['file'] . ':' . ($frame['line'] ?? 0) : '[interno]';
            $frames .= sprintf('<li><span class="index">#%d</span> <code>%s</code><br><small>%s</small></li>', $index, $this->e($call), $this->e($location));
        }

        return sprintf(
            '<section class="exception"><p class="class">%s%s</p><h1>%s</h1><p class="location">%s:%d</p>%s<h2>Stack trace</h2><ol class="trace">%s</ol></section>',
            $main ? '' : 'Causada por ',
            $this->e($exception::class),
            $this->e($exception->getMessage() !== '' ? $exception->getMessage() : '(sem mensagem)'),
            $this->e($exception->getFile()),
            $exception->getLine(),
            $this->codeExcerpt($exception->getFile(), $exception->getLine()),
            $frames,
        );
    }

    private function codeExcerpt(string $file, int $line): string
    {
        if (! is_file($file) || ! is_readable($file)) {
            return '';
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return '';
        }

        $start = max(1, $line - $this->contextLines);
        $end = min(count($lines), $line + $this->contextLines);
        $html = '';

        for ($number = $start; $number <= $end; $number++) {
            $html .= sprintf(
                '<span class="line%s"><span class="number">%d</span>%s</span>',
                $number === $line ? ' highlight' : '',
                $number,
                $this->e($lines[$number - 1]),
            );
        }

        return '<pre class="code">' . $html . '</pre>';
    }

    private function requestTable(ServerRequestInterface $request): string
    {
        $rows = '';

        foreach ($request->getHeaders() as $name => $values) {
            $rows .= sprintf('<tr><th>%s</th><td>%s</td></tr>', $this->e($name), $this->e(implode(', ', $values)));
        }

        foreach (['Query' => $request->getQueryParams(), 'Corpo' => $request->getParsedBody()] as $label => $data) {
            if ($data !== null && $data !== []) {
                $rows .= sprintf('<tr><th>%s</th><td><code>%s</code></td></tr>', $label, $this->e((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
            }
        }

        return '<table>' . $rows . '</table>';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private const string STYLE = <<<'CSS'
        *{box-sizing:border-box}body{margin:0;font:14px/1.5 system-ui,sans-serif;background:#f4f4f5;color:#18181b}
        header{background:#18181b;color:#fafafa;padding:12px 24px;font-family:ui-monospace,monospace;word-break:break-all}
        .status{background:#dc2626;padding:2px 8px;border-radius:4px;font-weight:700}.method{font-weight:700}
        section{background:#fff;margin:16px;padding:16px 24px;border-radius:8px;box-shadow:0 1px 2px #0001;overflow:auto}
        h1{font-size:20px;margin:4px 0}h2{font-size:15px;margin:16px 0 8px}.class{color:#dc2626;margin:0;font-family:ui-monospace,monospace}
        .location{color:#52525b;margin:0;font-family:ui-monospace,monospace;word-break:break-all}
        .code{background:#18181b;color:#e4e4e7;padding:8px 0;border-radius:6px;overflow:auto}
        .line{display:block;padding:0 12px;white-space:pre}.highlight{background:#7f1d1d}
        .number{display:inline-block;width:48px;color:#71717a;user-select:none}
        .trace{padding-left:0;list-style:none;font-family:ui-monospace,monospace}.trace li{padding:6px 0;border-bottom:1px solid #e4e4e7;word-break:break-all}
        .index{color:#71717a}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:4px 8px;border-bottom:1px solid #e4e4e7;vertical-align:top}
        th{width:200px;color:#52525b}td code{white-space:pre-wrap}
        CSS;
}
