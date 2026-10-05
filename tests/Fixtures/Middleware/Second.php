<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Middleware;

final class Second extends AppendHeader
{
    public function __construct()
    {
        parent::__construct('second');
    }
}
