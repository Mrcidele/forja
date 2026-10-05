<?php

declare(strict_types=1);

namespace Forja\RateLimit;

use Closure;
use Psr\SimpleCache\CacheInterface;

/**
 * Limitador por janela fixa: conta tentativas por chave dentro de uma janela
 * de N segundos, guardando o contador em qualquer cache PSR-16.
 */
final readonly class RateLimiter
{
    /** @var Closure(): int */
    private Closure $clock;

    /**
     * @param (Closure(): int)|null $clock
     */
    public function __construct(
        private CacheInterface $cache,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? time(...);
    }

    public function hit(string $key, int $maxAttempts, int $decaySeconds): RateLimit
    {
        $now = $this->now();
        $cacheKey = $this->cacheKey($key);
        $state = $this->state($cacheKey);

        if ($state === null || $state['resetsAt'] <= $now) {
            $state = ['attempts' => 0, 'resetsAt' => $now + $decaySeconds];
        }

        $state['attempts']++;
        $this->cache->set($cacheKey, $state, max(1, $state['resetsAt'] - $now));

        return new RateLimit($maxAttempts, $state['attempts'], $state['resetsAt']);
    }

    public function clear(string $key): void
    {
        $this->cache->delete($this->cacheKey($key));
    }

    public function now(): int
    {
        return ($this->clock)();
    }

    /**
     * @return array{attempts: int, resetsAt: int}|null
     */
    private function state(string $cacheKey): ?array
    {
        $state = $this->cache->get($cacheKey);

        if (! is_array($state) || ! is_int($state['attempts'] ?? null) || ! is_int($state['resetsAt'] ?? null)) {
            return null;
        }

        return ['attempts' => $state['attempts'], 'resetsAt' => $state['resetsAt']];
    }

    private function cacheKey(string $key): string
    {
        return 'rate-limit.' . hash('xxh128', $key);
    }
}
