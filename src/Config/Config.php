<?php

declare(strict_types=1);

namespace Forja\Config;

use InvalidArgumentException;
use stdClass;

/**
 * Repositório de configuração com acesso por notação de ponto
 * ("database.connections.sqlite.path").
 */
final class Config
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(
        private array $items = [],
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function has(string $key): bool
    {
        $missing = new stdClass();

        return $this->get($key, $missing) !== $missing;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target = &$this->items;

        foreach (array_slice($segments, 0, -1) as $segment) {
            if (! isset($target[$segment]) || ! is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target[$segments[array_key_last($segments)]] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function string(string $key, ?string $default = null): string
    {
        $value = $this->get($key, $default);

        return is_string($value) ? $value : throw $this->typeError($key, 'string', $value);
    }

    public function int(string $key, ?int $default = null): int
    {
        $value = $this->get($key, $default);

        return is_int($value) ? $value : throw $this->typeError($key, 'int', $value);
    }

    public function bool(string $key, ?bool $default = null): bool
    {
        $value = $this->get($key, $default);

        return is_bool($value) ? $value : throw $this->typeError($key, 'bool', $value);
    }

    /**
     * @param array<mixed>|null $default
     *
     * @return array<mixed>
     */
    public function array(string $key, ?array $default = null): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : throw $this->typeError($key, 'array', $value);
    }

    private function typeError(string $key, string $expected, mixed $value): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('A configuração [%s] deveria ser %s, mas é %s.', $key, $expected, get_debug_type($value)));
    }
}
