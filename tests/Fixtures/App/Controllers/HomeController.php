<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\App\Controllers;

use Forja\Config\Config;
use Forja\Routing\Attribute\Route;

final readonly class HomeController
{
    public function __construct(private Config $config)
    {
    }

    #[Route('/', name: 'home')]
    public function index(): string
    {
        return 'Bem-vindo à ' . $this->config->string('app.name');
    }

    #[Route('/api/boom')]
    public function boom(): never
    {
        throw new \RuntimeException('explodiu');
    }
}
