<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Container;

final class CircularB
{
    public function __construct(public CircularA $a)
    {
    }
}
