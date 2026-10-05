<?php

declare(strict_types=1);

namespace Forja\Session;

/**
 * Configuração do cookie e do tempo de vida da sessão.
 */
final readonly class SessionOptions
{
    /**
     * @param int $lifetime segundos de inatividade até a sessão expirar
     * @param 'Lax'|'Strict'|'None' $sameSite
     */
    public function __construct(
        public string $cookieName = 'forja_session',
        public int $lifetime = 7200,
        public string $path = '/',
        public ?string $domain = null,
        public bool $secure = false,
        public bool $httpOnly = true,
        public string $sameSite = 'Lax',
    ) {
    }
}
