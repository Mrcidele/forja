<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Events;

use Forja\Events\StoppableEvent;

final class UserRegistered extends StoppableEvent implements Auditable
{
    /** @var list<string> */
    public array $log = [];

    public function __construct(public readonly string $email)
    {
    }
}
