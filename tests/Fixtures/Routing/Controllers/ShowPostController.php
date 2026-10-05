<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Routing\Controllers;

use Forja\Routing\Attribute\Route;

#[Route('/posts/{slug:slug}', name: 'posts.show')]
final class ShowPostController
{
    public function __invoke(string $slug): string
    {
        return "<h1>{$slug}</h1>";
    }
}
