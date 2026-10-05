<?php

declare(strict_types=1);

namespace Forja\Database\Query\Grammar;

final class MySqlGrammar extends Grammar
{
    protected string $quote = '`';

    public function compileLimit(?int $limit, ?int $offset): string
    {
        // O MySQL exige LIMIT junto com OFFSET; usa o maior valor possível.
        return $limit === null && $offset !== null
            ? 'limit 18446744073709551615 offset ' . $offset
            : parent::compileLimit($limit, $offset);
    }
}
