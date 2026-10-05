<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Container;

final readonly class FixedClock implements ClockInterface
{
    public function __construct(private string $time = '2026-01-01')
    {
    }

    public function now(): string
    {
        return $this->time;
    }
}
