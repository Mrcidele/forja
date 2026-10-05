<?php

declare(strict_types=1);

namespace Forja\Database;

use InvalidArgumentException;

/**
 * Parâmetros de uma conexão, já tipados.
 */
final readonly class DatabaseConfig
{
    /**
     * @param 'sqlite'|'mysql'|'pgsql' $driver
     * @param array<int, mixed> $options atributos extras do PDO
     */
    public function __construct(
        public string $driver = 'sqlite',
        public string $database = ':memory:',
        public string $host = '127.0.0.1',
        public ?int $port = null,
        public string $username = '',
        public string $password = '',
        public string $charset = 'utf8mb4',
        public array $options = [],
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $driver = $config['driver'] ?? 'sqlite';

        if (! in_array($driver, ['sqlite', 'mysql', 'pgsql'], true)) {
            throw new InvalidArgumentException(sprintf('Driver de banco não suportado: [%s].', is_string($driver) ? $driver : get_debug_type($driver)));
        }

        $port = $config['port'] ?? null;
        $options = [];

        foreach (is_array($config['options'] ?? null) ? $config['options'] : [] as $attribute => $value) {
            if (is_int($attribute)) {
                $options[$attribute] = $value;
            }
        }

        return new self(
            driver: $driver,
            database: self::string($config, 'database', ':memory:'),
            host: self::string($config, 'host', '127.0.0.1'),
            port: is_numeric($port) ? (int) $port : null,
            username: self::string($config, 'username', ''),
            password: self::string($config, 'password', ''),
            charset: self::string($config, 'charset', $driver === 'pgsql' ? 'utf8' : 'utf8mb4'),
            options: $options,
        );
    }

    public function dsn(): string
    {
        return match ($this->driver) {
            'sqlite' => 'sqlite:' . $this->database,
            'mysql' => sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $this->host, $this->port ?? 3306, $this->database, $this->charset),
            'pgsql' => sprintf("pgsql:host=%s;port=%d;dbname=%s;options='--client_encoding=%s'", $this->host, $this->port ?? 5432, $this->database, $this->charset),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function string(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }
}
