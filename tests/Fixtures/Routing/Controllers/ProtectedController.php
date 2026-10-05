<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Routing\Controllers;

use Forja\Routing\Attribute\Group;
use Forja\Routing\Attribute\Middleware;
use Forja\Routing\Attribute\Route;
use Forja\Tests\Fixtures\Middleware\First;
use Forja\Tests\Fixtures\Middleware\Second;
use Forja\Tests\Fixtures\Middleware\Third;
use Psr\Http\Message\ServerRequestInterface;

#[Group('/protected', middleware: [First::class])]
#[Middleware(Second::class)]
final class ProtectedController
{
    /**
     * @return array<mixed>
     */
    #[Route('/trace', middleware: [Third::class])]
    public function trace(ServerRequestInterface $request): array
    {
        $trace = $request->getAttribute('trace', []);

        return is_array($trace) ? $trace : [];
    }

    /**
     * @return array<mixed>
     */
    #[Route('/method')]
    #[Middleware(Third::class)]
    public function method(ServerRequestInterface $request): array
    {
        return $this->trace($request);
    }
}
