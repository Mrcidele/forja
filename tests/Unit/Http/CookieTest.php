<?php

declare(strict_types=1);

use Forja\Http\Cookie;

it('monta o header Set-Cookie', function (): void {
    $cookie = new Cookie('tema', 'escuro azul', 60, '/app', 'forja.test', secure: true, sameSite: 'Strict');

    expect($cookie->toHeader(0))->toBe('tema=escuro%20azul; Expires=Thu, 01 Jan 1970 00:01:00 GMT; Max-Age=60; Path=/app; Domain=forja.test; Secure; HttpOnly; SameSite=Strict');
});

it('cria cookies de sessão do navegador e cookies expirados', function (): void {
    expect(new Cookie('a', 'b')->toHeader())->toBe('a=b; Path=/; HttpOnly; SameSite=Lax')
        ->and(Cookie::expired('a')->toHeader(100))->toBe('a=; Expires=Thu, 01 Jan 1970 00:01:39 GMT; Max-Age=0; Path=/; HttpOnly; SameSite=Lax');
});

it('força Secure com SameSite=None', function (): void {
    expect(new Cookie('a', sameSite: 'None')->toHeader())->toContain('; Secure;');
});

it('rejeita nomes inválidos', function (): void {
    new Cookie('a b');
})->throws(InvalidArgumentException::class);
