<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Events;

final class SendWelcomeEmail
{
    public function handle(UserRegistered $event): void
    {
        $event->log[] = 'email:' . $event->email;
    }
}
