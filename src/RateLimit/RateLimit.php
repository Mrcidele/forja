<?php

declare(strict_types=1);

namespace Forja\RateLimit;

/**
 * Estado de uma chave depois de registrar uma tentativa.
 */
final readonly class RateLimit
{
    public function __construct(
        public int $limit,
        public int $attempts,
        public int $resetsAt,
    ) {
    }

    public function remaining(): int
    {
        return max(0, $this->limit - $this->attempts);
    }

    public function exceeded(): bool
    {
        return $this->attempts > $this->limit;
    }

    public function retryAfter(int $now): int
    {
        return max(0, $this->resetsAt - $now);
    }
}
