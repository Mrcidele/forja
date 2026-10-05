<?php

declare(strict_types=1);

namespace Forja\Database\Query\Grammar;

final class SqliteGrammar extends Grammar
{
    public function compileLimit(?int $limit, ?int $offset): string
    {
        // O SQLite só aceita OFFSET acompanhado de LIMIT; -1 significa "sem limite".
        return parent::compileLimit($limit ?? ($offset !== null ? -1 : null), $offset);
    }
}
