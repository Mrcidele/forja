<?php

declare(strict_types=1);

namespace Forja\Cache;

use DateInterval;

/**
 * Cache em memória: dura o tempo de vida do processo (útil em testes e em worker mode).
 */
final class ArrayCache extends AbstractCache
{
    /** @var array<string, array{mixed, int}> chave => [valor, expiração] */
    private array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->has($key) ? $this->items[$key][0] : $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->assertKey($key);
        $expiration = $this->expiration($ttl);

        if ($expiration === null) {
            unset($this->items[$key]);

            return true;
        }

        $this->items[$key] = [is_object($value) ? clone $value : $value, $expiration];

        return true;
    }

    public function delete(string $key): bool
    {
        $this->assertKey($key);
        unset($this->items[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function has(string $key): bool
    {
        $this->assertKey($key);

        if (! isset($this->items[$key])) {
            return false;
        }

        $expiration = $this->items[$key][1];

        if ($expiration !== 0 && $expiration <= $this->now()) {
            unset($this->items[$key]);

            return false;
        }

        return true;
    }
}
