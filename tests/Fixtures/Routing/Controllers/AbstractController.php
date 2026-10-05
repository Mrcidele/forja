<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Routing\Controllers;

use Forja\Routing\Attribute\Route;

abstract class AbstractController
{
    #[Route('/abstrata')]
    public function ignored(): string
    {
        return 'nunca registrada';
    }
}
