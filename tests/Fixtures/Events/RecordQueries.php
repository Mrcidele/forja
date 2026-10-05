<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Events;

use Forja\Database\Event\QueryExecuted;

final class RecordQueries
{
    /** @var list<string> */
    public static array $queries = [];

    public function __invoke(QueryExecuted $event): void
    {
        self::$queries[] = $event->sql;
    }
}
