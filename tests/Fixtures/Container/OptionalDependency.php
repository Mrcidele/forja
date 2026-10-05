<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Container;

final class OptionalDependency
{
    public function __construct(public ?ClockInterface $clock = null, public int $retries = 3)
    {
    }
}
