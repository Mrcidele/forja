<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Routing;

final class UserRepository
{
    /** @var array<int, string> */
    private array $users = [1 => 'Ada', 2 => 'Linus'];

    public function find(int $id): ?string
    {
        return $this->users[$id] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return $this->users;
    }
}
