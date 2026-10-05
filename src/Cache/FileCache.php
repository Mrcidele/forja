<?php

declare(strict_types=1);

namespace Forja\Cache;

use Closure;
use DateInterval;
use FilesystemIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Cache em arquivos: um arquivo por chave com a expiração na primeira linha
 * e o valor serializado em seguida. Gravações são atômicas (arquivo
 * temporário + rename).
 */
final class FileCache extends AbstractCache
{
    /**
     * @param (Closure(): int)|null $clock
     */
    public function __construct(
        private readonly string $directory,
        ?Closure $clock = null,
    ) {
        parent::__construct($clock);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->read($key);

        return $entry === null ? $default : $entry[0];
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->assertKey($key);
        $expiration = $this->expiration($ttl);

        if ($expiration === null) {
            return $this->delete($key);
        }

        $this->ensureDirectory();
        $file = $this->path($key);
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (file_put_contents($temporary, $expiration . "\n" . serialize($value)) === false) {
            return false;
        }

        return rename($temporary, $file);
    }

    public function delete(string $key): bool
    {
        $this->assertKey($key);
        $file = $this->path($key);

        return ! is_file($file) || unlink($file);
    }

    public function clear(): bool
    {
        if (! is_dir($this->directory)) {
            return true;
        }

        $success = true;

        foreach (new FilesystemIterator($this->directory) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.cache')) {
                $success = unlink($file->getPathname()) && $success;
            }
        }

        return $success;
    }

    public function has(string $key): bool
    {
        return $this->read($key) !== null;
    }

    /**
     * @return array{mixed}|null
     */
    private function read(string $key): ?array
    {
        $this->assertKey($key);
        $file = $this->path($key);

        if (! is_file($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        if ($contents === false || ! str_contains($contents, "\n")) {
            return null;
        }

        [$expiration, $payload] = explode("\n", $contents, 2);

        if ((int) $expiration !== 0 && (int) $expiration <= $this->now()) {
            $this->delete($key);

            return null;
        }

        // Os arquivos são gravados apenas por esta classe, nunca vêm do cliente.
        $value = unserialize($payload);

        if ($value === false && $payload !== serialize(false)) {
            return null;
        }

        return [$value];
    }

    private function path(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . hash('xxh128', $key) . '.cache';
    }

    private function ensureDirectory(): void
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0o775, true) && ! is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Não foi possível criar o diretório de cache [%s].', $this->directory));
        }
    }
}
