<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Database;

use Forja\Database\ORM\Attribute\BelongsTo;
use Forja\Database\ORM\Attribute\Column;
use Forja\Database\ORM\Attribute\Id;
use Forja\Database\ORM\Attribute\Table;

#[Table('posts')]
final class Post
{
    #[Id]
    public ?int $id = null;

    #[BelongsTo(User::class, foreignKey: 'user_id')]
    public ?User $author = null;

    public function __construct(
        #[Column] public int $userId,
        #[Column] public string $title,
        #[Column] public float $rating = 0.0,
    ) {
    }
}
