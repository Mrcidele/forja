<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Container;

final class Variadic
{
    /** @var list<ClockInterface> */
    public array $clocks;

    public function __construct(ClockInterface ...$clocks)
    {
        $this->clocks = array_values($clocks);
    }
}
