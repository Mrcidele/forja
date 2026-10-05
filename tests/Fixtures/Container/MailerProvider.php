<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Container;

use Forja\Container\Container;
use Forja\Container\ServiceProvider;

final class MailerProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->bind(Mailer::class, static fn (Logger $logger): Mailer => new Mailer($logger, 'provider@forja.test'));
    }
}
