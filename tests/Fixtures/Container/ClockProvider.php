<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Container;

use ArrayObject;
use Forja\Container\Container;
use Forja\Container\ServiceProvider;

final class ClockProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(ClockInterface::class, static fn (): FixedClock => new FixedClock('provider'));
        $container->instance('boot.log', new ArrayObject());
    }

    public function boot(ClockInterface $clock, Container $container): void
    {
        /** @var ArrayObject<int, string> $log */
        $log = $container->get('boot.log');
        $log[] = 'clock:' . $clock->now();
    }
}
