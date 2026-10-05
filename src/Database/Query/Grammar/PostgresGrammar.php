<?php

declare(strict_types=1);

namespace Forja\Database\Query\Grammar;

final class PostgresGrammar extends Grammar
{
    public function supportsReturning(): bool
    {
        return true;
    }
}
