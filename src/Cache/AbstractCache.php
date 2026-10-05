<?php

declare(strict_types=1);

namespace Forja\Cache;

use Closure;
use DateInterval;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;

/**
 * Validação de chaves, cálculo de expiração e operações em lote comuns às
 * implementações PSR-16.
 */
abstract class AbstractCache implements CacheInterface
{
    /** @var Closure(): int */
    private readonly Closure $clock;

    /**
     * @param (Closure(): int)|null $clock relógio injetável (timestamp em segundos)
     */
    public function __construct(?Closure $clock = null)
    {
        $this->clock = $clock ?? time(...);
    }

    /**
     * @param iterable<string> $keys
     *
     * @return array<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): array
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * @param iterable<mixed, mixed> $values
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            $success = $this->set($this->key($key), $value, $ttl) && $success;
        }

        return $success;
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            $success = $this->delete($key) && $success;
        }

        return $success;
    }

    protected function now(): int
    {
        return ($this->clock)();
    }

    protected function assertKey(string $key): void
    {
        if ($key === '' || strpbrk($key, '{}()/\@:') !== false) {
            throw InvalidArgumentException::invalidKey($key);
        }
    }

    /**
     * Timestamp de expiração; 0 significa "nunca expira" e null indica que o
     * TTL já venceu (o item deve ser removido).
     */
    protected function expiration(DateInterval|int|null $ttl): ?int
    {
        if ($ttl === null) {
            return 0;
        }

        $seconds = $ttl instanceof DateInterval
            ? new DateTimeImmutable('@0')->add($ttl)->getTimestamp()
            : $ttl;

        return $seconds > 0 ? $this->now() + $seconds : null;
    }

    private function key(mixed $key): string
    {
        if (! is_string($key) && ! is_int($key)) {
            throw InvalidArgumentException::invalidKey(get_debug_type($key));
        }

        return (string) $key;
    }
}
