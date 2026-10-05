<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\App;

use Forja\Container\Container;
use Forja\Container\ServiceProvider;

final class FixtureProvider extends ServiceProvider
{
    public static int $boots = 0;

    public function register(Container $container): void
    {
        $container->instance('fixture.registered', true);
    }

    public function boot(): void
    {
        self::$boots++;
    }
}
