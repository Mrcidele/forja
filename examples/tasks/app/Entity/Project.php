<?php

declare(strict_types=1);

namespace App\Entity;

use Forja\Database\ORM\Attribute\Column;
use Forja\Database\ORM\Attribute\HasMany;
use Forja\Database\ORM\Attribute\Id;
use Forja\Database\ORM\Attribute\Table;

#[Table('projects')]
final class Project
{
    #[Id]
    public ?int $id = null;

    /** @var list<Task> */
    #[HasMany(Task::class, foreignKey: 'project_id')]
    public array $tasks = [];

    public function __construct(
        #[Column] public string $name,
    ) {
    }
}
