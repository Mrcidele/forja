<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Database;

use Forja\Database\ORM\Repository;

/**
 * @extends Repository<User>
 */
final class UserRepository extends Repository
{
    /**
     * @return list<User>
     */
    public function admins(): array
    {
        return $this->findBy(['role' => Role::Admin]);
    }

    protected function entity(): string
    {
        return User::class;
    }
}
