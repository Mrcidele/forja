<?php

declare(strict_types=1);

namespace Forja\Database\Query\Grammar;

use Forja\Database\Expression;

/**
 * Regras de SQL que variam entre bancos. A base segue o padrão ANSI
 * (identificadores entre aspas duplas), usado por SQLite e PostgreSQL.
 */
class Grammar
{
    protected string $quote = '"';

    /**
     * Envolve identificadores: "users.name", "name as nome", "*" e expressões cruas.
     */
    public function wrap(string|Expression $value): string
    {
        if ($value instanceof Expression) {
            return $value->value;
        }

        if (preg_match('/^(.+?)\s+as\s+(.+)$/i', $value, $matches) === 1) {
            return $this->wrap($matches[1]) . ' as ' . $this->wrapSegment($matches[2]);
        }

        return implode('.', array_map($this->wrapSegment(...), explode('.', $value)));
    }

    /**
     * @param list<string|Expression> $values
     */
    public function columnize(array $values): string
    {
        return implode(', ', array_map($this->wrap(...), $values));
    }

    public function wrapSegment(string $segment): string
    {
        $segment = trim($segment);

        if ($segment === '*') {
            return $segment;
        }

        return $this->quote . str_replace($this->quote, $this->quote . $this->quote, $segment) . $this->quote;
    }

    public function compileLimit(?int $limit, ?int $offset): string
    {
        $sql = $limit === null ? '' : 'limit ' . $limit;

        if ($offset !== null) {
            return trim($sql . ' offset ' . $offset);
        }

        return $sql;
    }

    /**
     * Se o banco devolve o ID inserido com "INSERT ... RETURNING".
     */
    public function supportsReturning(): bool
    {
        return false;
    }
}
