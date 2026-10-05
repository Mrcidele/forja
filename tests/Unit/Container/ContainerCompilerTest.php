<?php

declare(strict_types=1);

use Forja\Container\Container;
use Forja\Container\ContainerCompiler;
use Forja\Tests\Fixtures\Container\ClockInterface;
use Forja\Tests\Fixtures\Container\FixedClock;
use Forja\Tests\Fixtures\Container\Logger;
use Forja\Tests\Fixtures\Container\Mailer;
use Forja\Tests\Fixtures\Container\Mode;
use Forja\Tests\Fixtures\Container\NeedsScalar;
use Forja\Tests\Fixtures\Container\NoConstructor;
use Forja\Tests\Fixtures\Container\WithDefaults;
use Forja\Tests\Fixtures\Container\WithObjectDefault;

beforeEach(function (): void {
    $this->file = sys_get_temp_dir() . '/forja-container-' . bin2hex(random_bytes(4)) . '/container.php';
});

afterEach(function (): void {
    if (is_file($this->file)) {
        unlink($this->file);
        rmdir(dirname($this->file));
    }
});

it('gera fábricas sem reflection para as classes informadas', function (): void {
    $code = new ContainerCompiler()->compile([Mailer::class, NoConstructor::class]);

    expect($code)
        ->toContain(var_export(Mailer::class, true) . ' => static fn (\\Forja\\Container\\Container $c): \\' . Mailer::class)
        ->toContain('new \\' . Mailer::class . '($c->get(' . var_export(Logger::class, true) . "), 'noreply@forja.test')")
        ->toContain('new \\' . NoConstructor::class . '()');
});

it('exporta enums, arrays e dependências opcionais', function (): void {
    $code = new ContainerCompiler()->compile([WithDefaults::class]);

    $clock = var_export(ClockInterface::class, true);

    expect($code)
        ->toContain('\\' . Mode::class . '::Safe)')
        ->toContain("[0 => 'a', 1 => 'b'], (\$c->has({$clock}) ? \$c->get({$clock}) : null)");
});

it('ignora classes que não podem ser compiladas', function (): void {
    $code = new ContainerCompiler()->compile([NeedsScalar::class, WithObjectDefault::class, ClockInterface::class, 'Inexistente']);

    expect($code)->not->toContain('=> static fn');
});

it('grava o arquivo e o container passa a usar as fábricas compiladas', function (): void {
    new ContainerCompiler()->dump([Mailer::class, Logger::class, WithDefaults::class], $this->file);

    $container = new Container();
    $container->bind(ClockInterface::class, FixedClock::class);
    $container->loadCompiled(ContainerCompiler::load($this->file));

    $mailer = $container->get(Mailer::class);
    $withDefaults = $container->get(WithDefaults::class);

    expect($mailer->logger->clock)->toBeInstanceOf(FixedClock::class)
        ->and($withDefaults->mode)->toBe(Mode::Safe)
        ->and($withDefaults->clock)->toBeInstanceOf(FixedClock::class)
        ->and($container->autowiredClasses())->toBe([FixedClock::class]);
});

it('registra quais classes foram construídas por reflection', function (): void {
    $container = new Container();
    $container->bind(ClockInterface::class, FixedClock::class);
    $container->get(Mailer::class);

    expect($container->autowiredClasses())->toBe([Mailer::class, Logger::class, FixedClock::class]);
});
