<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Container;

interface ClockInterface
{
    public function now(): string;
}
