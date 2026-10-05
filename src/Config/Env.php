<?php

declare(strict_types=1);

namespace Forja\Config;

use Dotenv\Dotenv;

/**
 * Leitura de variáveis de ambiente, carregadas do sistema ou de um arquivo .env.
 */
final class Env
{
    /**
     * Carrega o arquivo .env sem sobrescrever variáveis já definidas no
     * ambiente real. Arquivo ausente não é erro.
     */
    public static function load(string $directory, string $file = '.env'): void
    {
        if (is_file(rtrim($directory, '/') . '/' . $file)) {
            Dotenv::createImmutable($directory, $file)->load();
        }
    }

    /**
     * Converte "true", "false", "null" e "empty" (com ou sem parênteses) nos
     * valores correspondentes.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::raw($key);

        if ($value === null) {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = filter_var(self::raw($key), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return is_int($value) ? $value : $default;
    }

    private static function raw(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_scalar($value) && $value !== false ? (string) $value : null;
    }
}
