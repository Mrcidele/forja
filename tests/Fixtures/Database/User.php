<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Database;

use DateTimeImmutable;
use Forja\Database\ORM\Attribute\Column;
use Forja\Database\ORM\Attribute\HasMany;
use Forja\Database\ORM\Attribute\Id;
use Forja\Database\ORM\Attribute\Table;

#[Table('users')]
final class User
{
    #[Id]
    public ?int $id = null;

    /** @var list<Post> */
    #[HasMany(Post::class, foreignKey: 'user_id')]
    public array $posts = [];

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        #[Column] public string $name,
        #[Column(name: 'email_address')] public string $email,
        #[Column] public bool $active = true,
        #[Column] public Role $role = Role::Member,
        #[Column] public array $settings = [],
        #[Column] public ?DateTimeImmutable $createdAt = null,
    ) {
    }
}
