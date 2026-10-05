<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Routing;

enum Status: string
{
    case Active = 'active';
    case Archived = 'archived';
}
