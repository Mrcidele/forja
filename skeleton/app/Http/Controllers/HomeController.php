<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Forja\Config\AppConfig;
use Forja\Routing\Attribute\Route;
use Forja\View\Engine;

final readonly class HomeController
{
    public function __construct(
        private Engine $views,
        private AppConfig $app,
    ) {
    }

    #[Route('/', name: 'home')]
    public function index(): string
    {
        return $this->views->render('home', ['name' => $this->app->name]);
    }

    /**
     * @return array{status: string}
     */
    #[Route('/api/health', name: 'health')]
    public function health(): array
    {
        return ['status' => 'ok'];
    }
}
