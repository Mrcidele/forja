<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Container;

final class WithObjectDefault
{
    public function __construct(public FixedClock $clock = new FixedClock('padrão'))
    {
    }
}
