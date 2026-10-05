<?php

declare(strict_types=1);

namespace Forja\Log\Handler;

use DateTimeInterface;
use Forja\Log\Level;
use Forja\Log\LogRecord;
use JsonSerializable;
use RuntimeException;
use Stringable;
use Throwable;

/**
 * Grava cada registro como uma linha em um arquivo ou stream (php://stderr):
 * [2026-10-05T12:00:00+00:00] app.ERROR: mensagem {"contexto":"..."}
 */
final class StreamHandler implements HandlerInterface
{
    /** @var resource|null */
    private $stream;

    private string $path = '';

    /**
     * @param string|resource $target caminho do arquivo, URL de stream ou recurso aberto
     */
    public function __construct(
        mixed $target,
        private readonly Level $level = Level::Debug,
    ) {
        if (is_resource($target)) {
            $this->stream = $target;
        } elseif (is_string($target) && $target !== '') {
            $this->path = $target;
        } else {
            throw new RuntimeException('O destino do log deve ser um caminho ou um recurso.');
        }
    }

    public function handles(Level $level): bool
    {
        return $this->level->includes($level);
    }

    public function handle(LogRecord $record): void
    {
        fwrite($this->stream(), $this->format($record));
    }

    public function format(LogRecord $record): string
    {
        $line = sprintf('[%s] %s.%s: %s', $record->datetime->format(DATE_ATOM), $record->channel, $record->level->label(), $record->message);
        $context = $this->normalize($record->context);

        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        return str_replace(["\r", "\n"], ['\r', '\n'], $line) . PHP_EOL;
    }

    /**
     * @return resource
     */
    private function stream()
    {
        if ($this->stream !== null) {
            return $this->stream;
        }

        $path = $this->path;

        if (! str_contains($path, '://')) {
            $directory = dirname($path);

            if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
                throw new RuntimeException(sprintf('Não foi possível criar o diretório de logs [%s].', $directory));
            }
        }

        $stream = fopen($path, 'ab');

        if ($stream === false) {
            throw new RuntimeException(sprintf('Não foi possível abrir o arquivo de log [%s].', $path));
        }

        return $this->stream = $stream;
    }

    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>
     */
    private function normalize(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            $normalized[$key] = match (true) {
                $value instanceof Throwable => [
                    'class' => $value::class,
                    'message' => $value->getMessage(),
                    'file' => $value->getFile() . ':' . $value->getLine(),
                ],
                $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
                $value instanceof JsonSerializable => $value,
                $value instanceof Stringable => (string) $value,
                is_object($value) => '[objeto ' . $value::class . ']',
                is_resource($value) => '[recurso]',
                is_array($value) => $this->normalize($value),
                default => $value,
            };
        }

        return $normalized;
    }
}
