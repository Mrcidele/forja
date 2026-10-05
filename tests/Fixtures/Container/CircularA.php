<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Container;

final class CircularA
{
    public function __construct(public CircularB $b)
    {
    }
}
