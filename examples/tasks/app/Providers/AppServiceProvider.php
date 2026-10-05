<?php

declare(strict_types=1);

namespace App\Providers;

use Forja\Container\Container;
use Forja\Container\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // $container->singleton(Interface::class, Implementacao::class);
    }
}
