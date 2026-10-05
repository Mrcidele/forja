<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Database;

enum Role: string
{
    case Admin = 'admin';
    case Member = 'member';
}
