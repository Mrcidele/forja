<?php

declare(strict_types=1);

namespace Forja\Http;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Valor de um header Set-Cookie.
 */
final readonly class Cookie
{
    /**
     * @param int $maxAge segundos; 0 cria um cookie de sessão do navegador e negativo o expira
     * @param 'Lax'|'Strict'|'None' $sameSite
     */
    public function __construct(
        public string $name,
        public string $value = '',
        public int $maxAge = 0,
        public string $path = '/',
        public ?string $domain = null,
        public bool $secure = false,
        public bool $httpOnly = true,
        public string $sameSite = 'Lax',
    ) {
        if ($name === '' || preg_match('/[=,; \t\r\n\013\014]/', $name) === 1) {
            throw new InvalidArgumentException(sprintf('Nome de cookie inválido: [%s].', $name));
        }
    }

    public static function expired(string $name, string $path = '/', ?string $domain = null): self
    {
        return new self($name, '', -1, $path, $domain);
    }

    public function toHeader(?int $now = null): string
    {
        $parts = [$this->name . '=' . rawurlencode($this->value)];

        if ($this->maxAge !== 0) {
            $expires = new DateTimeImmutable('@' . (($now ?? time()) + $this->maxAge))->setTimezone(new DateTimeZone('UTC'));
            $parts[] = 'Expires=' . $expires->format('D, d M Y H:i:s \\G\\M\\T');
            $parts[] = 'Max-Age=' . max(0, $this->maxAge);
        }

        $parts[] = 'Path=' . $this->path;

        if ($this->domain !== null) {
            $parts[] = 'Domain=' . $this->domain;
        }

        if ($this->secure || $this->sameSite === 'None') {
            $parts[] = 'Secure';
        }

        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        $parts[] = 'SameSite=' . $this->sameSite;

        return implode('; ', $parts);
    }
}
