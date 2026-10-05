<?php

declare(strict_types=1);

namespace Forja\Session;

/**
 * Sessão da requisição atual, independente das funções session_* do PHP
 * (o que a mantém segura em worker mode).
 *
 * Valores "flash" ficam disponíveis apenas na requisição seguinte.
 */
final class Session
{
    private const string FLASH_NEW = '_flash.new';

    private const string FLASH_OLD = '_flash.old';

    private const string TOKEN = '_token';

    /** IDs de sessão: 40 caracteres hexadecimais. */
    public const string ID_PATTERN = '/^[a-f0-9]{40}$/';

    private ?string $previousId = null;

    private bool $destroyPrevious = false;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private string $id,
        private array $attributes = [],
    ) {
    }

    public static function generateId(): string
    {
        return bin2hex(random_bytes(20));
    }

    public static function isValidId(string $id): bool
    {
        return preg_match(self::ID_PATTERN, $id) === 1;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function remove(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Lê e remove um valor.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    public function isEmpty(): bool
    {
        return $this->attributes === [];
    }

    /**
     * Guarda um valor que sobrevive apenas até o fim da próxima requisição.
     */
    public function flash(string $key, mixed $value): void
    {
        $this->set($key, $value);
        $new = $this->keys(self::FLASH_NEW);
        $new[] = $key;
        $this->attributes[self::FLASH_NEW] = array_values(array_unique($new));
        $this->attributes[self::FLASH_OLD] = array_values(array_diff($this->keys(self::FLASH_OLD), [$key]));
    }

    /**
     * Executado no início de cada requisição: descarta os flashes da
     * requisição anterior e marca os atuais para descarte na próxima.
     */
    public function ageFlashData(): void
    {
        foreach ($this->keys(self::FLASH_OLD) as $key) {
            unset($this->attributes[$key]);
        }

        $this->attributes[self::FLASH_OLD] = $this->keys(self::FLASH_NEW);
        $this->attributes[self::FLASH_NEW] = [];

        if ($this->attributes[self::FLASH_OLD] === []) {
            unset($this->attributes[self::FLASH_OLD], $this->attributes[self::FLASH_NEW]);
        }
    }

    /**
     * Token CSRF da sessão, criado na primeira leitura.
     */
    public function token(): string
    {
        $token = $this->get(self::TOKEN);

        if (! is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->set(self::TOKEN, $token);
        }

        return $token;
    }

    /**
     * Gera um novo ID mantendo os dados (use após login para evitar fixação de sessão).
     */
    public function regenerate(bool $destroyPrevious = true): void
    {
        $this->previousId ??= $this->id;
        $this->destroyPrevious = $this->destroyPrevious || $destroyPrevious;
        $this->id = self::generateId();
    }

    /**
     * Remove todos os dados e gera um novo ID (logout).
     */
    public function invalidate(): void
    {
        $this->attributes = [];
        $this->regenerate();
    }

    /**
     * ID anterior que deve ser apagado do armazenamento, se houver.
     */
    public function previousIdToDestroy(): ?string
    {
        return $this->destroyPrevious ? $this->previousId : null;
    }

    /**
     * @return list<string>
     */
    private function keys(string $bucket): array
    {
        $keys = $this->attributes[$bucket] ?? [];

        return is_array($keys) ? array_values(array_filter($keys, is_string(...))) : [];
    }
}
