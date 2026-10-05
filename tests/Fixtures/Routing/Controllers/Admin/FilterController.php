<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Routing\Controllers\Admin;

use Forja\Routing\Attribute\Group;
use Forja\Routing\Attribute\Route;
use Forja\Tests\Fixtures\Routing\Priority;
use Forja\Tests\Fixtures\Routing\Status;

#[Group('/admin', 'admin.')]
final class FilterController
{
    /**
     * @return array{status: string, priority: int}
     */
    #[Route('/tasks/{status}/{priority}', name: 'tasks')]
    public function tasks(Status $status, Priority $priority): array
    {
        return ['status' => $status->value, 'priority' => $priority->value];
    }

    #[Route('/ratio/{value}')]
    public function ratio(float $value): string
    {
        return (string) ($value * 2);
    }

    #[Route('/empty', methods: ['DELETE'])]
    public function empty(): null
    {
        return null;
    }
}
