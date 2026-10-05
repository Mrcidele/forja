<?php

declare(strict_types=1);

namespace Forja\Config;

use ValueError;

/**
 * Ambiente de execução da aplicação.
 */
enum Environment: string
{
    case Production = 'production';
    case Staging = 'staging';
    case Development = 'development';
    case Testing = 'testing';

    /**
     * Aceita o nome completo ou apelidos comuns (prod, dev, local, test).
     */
    public static function fromName(string $name): self
    {
        $normalized = strtolower(trim($name));

        return match ($normalized) {
            'prod' => self::Production,
            'stage' => self::Staging,
            'dev', 'local' => self::Development,
            'test' => self::Testing,
            default => self::tryFrom($normalized) ?? throw new ValueError(sprintf('Ambiente desconhecido: [%s].', $name)),
        };
    }

    public function isProduction(): bool
    {
        return $this === self::Production;
    }

    public function isDevelopment(): bool
    {
        return $this === self::Development;
    }

    public function isTesting(): bool
    {
        return $this === self::Testing;
    }

    /**
     * Valor padrão de "debug" quando APP_DEBUG não está definido.
     */
    public function debugByDefault(): bool
    {
        return $this === self::Development || $this === self::Testing;
    }
}
