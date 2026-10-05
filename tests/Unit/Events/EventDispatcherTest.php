<?php

declare(strict_types=1);

use Forja\Container\Container;
use Forja\Events\EventDispatcher;
use Forja\Events\ListenerProvider;
use Forja\Tests\Fixtures\Events\Auditable;
use Forja\Tests\Fixtures\Events\SendWelcomeEmail;
use Forja\Tests\Fixtures\Events\UserRegistered;

beforeEach(function (): void {
    $this->provider = new ListenerProvider(new Container());
    $this->dispatcher = new EventDispatcher($this->provider);
});

it('entrega o evento aos ouvintes por prioridade e ordem de registro', function (): void {
    $this->provider->listen(UserRegistered::class, fn (UserRegistered $e): string => $e->log[] = 'segundo');
    $this->provider->listen(UserRegistered::class, fn (UserRegistered $e): string => $e->log[] = 'primeiro', priority: 10);
    $this->provider->listen(UserRegistered::class, fn (UserRegistered $e): string => $e->log[] = 'terceiro');

    $event = $this->dispatcher->dispatch(new UserRegistered('ada@forja.test'));

    expect($event->log)->toBe(['primeiro', 'segundo', 'terceiro']);
});

it('casa ouvintes por classe-mãe e interface', function (): void {
    $this->provider->listen(Auditable::class, fn (UserRegistered $e): string => $e->log[] = 'auditoria');
    $this->provider->listen(stdClass::class, fn () => throw new LogicException('não deveria executar'));

    expect($this->dispatcher->dispatch(new UserRegistered('x'))->log)->toBe(['auditoria']);
});

it('resolve ouvintes por classe pelo container, com handle() ou __invoke()', function (): void {
    $this->provider->listen(UserRegistered::class, SendWelcomeEmail::class);

    expect($this->dispatcher->dispatch(new UserRegistered('ada@forja.test'))->log)->toBe(['email:ada@forja.test'])
        ->and($this->provider->hasListeners(UserRegistered::class))->toBeTrue()
        ->and($this->provider->hasListeners(Auditable::class))->toBeFalse();
});

it('interrompe a propagação de eventos interrompíveis', function (): void {
    $this->provider->listen(UserRegistered::class, function (UserRegistered $e): void {
        $e->log[] = 'executado';
        $e->stopPropagation();
    });
    $this->provider->listen(UserRegistered::class, fn (UserRegistered $e): string => $e->log[] = 'ignorado');

    $event = $this->dispatcher->dispatch(new UserRegistered('x'));
    $stopped = new UserRegistered('y');
    $stopped->stopPropagation();

    expect($event->log)->toBe(['executado'])
        ->and($this->dispatcher->dispatch($stopped)->log)->toBe([]);
});

it('rejeita ouvintes que não podem ser chamados', function (): void {
    $this->provider->listen(UserRegistered::class, stdClass::class);

    $this->dispatcher->dispatch(new UserRegistered('x'));
})->throws(InvalidArgumentException::class, 'handle()');
