<?php

declare(strict_types=1);

namespace Forja\Session;

use Psr\SimpleCache\CacheInterface;

/**
 * Armazena sessões em qualquer cache PSR-16 (FileCache, ArrayCache, Redis...).
 */
final readonly class CacheSessionStore implements SessionStoreInterface
{
    public function __construct(
        private CacheInterface $cache,
        private string $prefix = 'session.',
    ) {
    }

    public function read(string $id): ?array
    {
        $data = $this->cache->get($this->prefix . $id);

        if (! is_array($data)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function write(string $id, array $data, int $lifetime): void
    {
        $this->cache->set($this->prefix . $id, $data, $lifetime);
    }

    public function destroy(string $id): void
    {
        $this->cache->delete($this->prefix . $id);
    }
}
