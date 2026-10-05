<?php

declare(strict_types=1);

use Forja\Session\Session;

it('guarda, lê e remove valores', function (): void {
    $session = new Session(Session::generateId());
    $session->set('user', 7);

    expect($session->get('user'))->toBe(7)
        ->and($session->has('user'))->toBeTrue()
        ->and($session->pull('user'))->toBe(7)
        ->and($session->has('user'))->toBeFalse()
        ->and($session->get('x', 'padrão'))->toBe('padrão')
        ->and($session->isEmpty())->toBeTrue();
});

it('gera IDs válidos e rejeita formatos inesperados', function (): void {
    expect(Session::isValidId(Session::generateId()))->toBeTrue()
        ->and(Session::isValidId('../../etc/passwd'))->toBeFalse()
        ->and(Session::isValidId(str_repeat('A', 40)))->toBeFalse();
});

it('mantém valores flash apenas até a próxima requisição', function (): void {
    $session = new Session(Session::generateId());
    $session->flash('status', 'Salvo!');

    $session->ageFlashData();
    expect($session->get('status'))->toBe('Salvo!');

    $session->ageFlashData();
    expect($session->has('status'))->toBeFalse()
        ->and($session->isEmpty())->toBeTrue();
});

it('regenera o ID mantendo os dados e invalida limpando tudo', function (): void {
    $session = new Session($original = Session::generateId(), ['user' => 1]);

    $session->regenerate();

    expect($session->id())->not->toBe($original)
        ->and($session->get('user'))->toBe(1)
        ->and($session->previousIdToDestroy())->toBe($original);

    $session->invalidate();

    expect($session->isEmpty())->toBeTrue()
        ->and($session->previousIdToDestroy())->toBe($original);
});

it('cria o token CSRF uma única vez', function (): void {
    $session = new Session(Session::generateId());

    expect($session->token())->toHaveLength(64)
        ->and($session->token())->toBe($session->token());
});
