<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Forja\Database\ORM\Attribute\BelongsTo;
use Forja\Database\ORM\Attribute\Column;
use Forja\Database\ORM\Attribute\Id;
use Forja\Database\ORM\Attribute\Table;

#[Table('tasks')]
final class Task
{
    #[Id]
    public ?int $id = null;

    #[BelongsTo(Project::class, foreignKey: 'project_id')]
    public ?Project $project = null;

    public function __construct(
        #[Column] public int $projectId,
        #[Column] public string $title,
        #[Column] public TaskStatus $status = TaskStatus::Pending,
        #[Column] public ?DateTimeImmutable $dueDate = null,
        #[Column] public DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
    }
}
