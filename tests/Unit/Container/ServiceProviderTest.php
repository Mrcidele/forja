<?php

declare(strict_types=1);

use Forja\Container\Container;
use Forja\Tests\Fixtures\Container\ClockInterface;
use Forja\Tests\Fixtures\Container\ClockProvider;
use Forja\Tests\Fixtures\Container\Mailer;
use Forja\Tests\Fixtures\Container\MailerProvider;

it('registra os bindings dos providers', function (): void {
    $container = new Container();
    $container->register(ClockProvider::class);
    $container->register(new MailerProvider());

    expect($container->get(ClockInterface::class)->now())->toBe('provider')
        ->and($container->get(Mailer::class)->from)->toBe('provider@forja.test')
        ->and($container->providers())->toBe([ClockProvider::class, MailerProvider::class]);
});

it('executa boot uma única vez, com injeção de dependências', function (): void {
    $container = new Container();
    $container->register(ClockProvider::class);

    expect($container->isBooted())->toBeFalse();

    $container->boot();
    $container->boot();

    expect($container->isBooted())->toBeTrue()
        ->and($container->get('boot.log')->getArrayCopy())->toBe(['clock:provider']);
});

it('inicializa imediatamente providers registrados após o boot', function (): void {
    $container = new Container();
    $container->boot();
    $container->register(ClockProvider::class);

    expect($container->get('boot.log')->getArrayCopy())->toBe(['clock:provider']);
});

it('ignora o registro duplicado de um provider', function (): void {
    $container = new Container();
    $first = $container->register(ClockProvider::class);

    expect($container->register(ClockProvider::class))->toBe($first)
        ->and($container->providers())->toBe([ClockProvider::class]);
});
