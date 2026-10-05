<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Middleware;

final class Third extends AppendHeader
{
    public function __construct()
    {
        parent::__construct('third');
    }
}
