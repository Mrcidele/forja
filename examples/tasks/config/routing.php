<?php

declare(strict_types=1);

return [
    // Diretórios com controllers que declaram rotas por atributos.
    'controllers' => [dirname(__DIR__) . '/app/Http/Controllers'],

    // Arquivos que retornam fn (RouteCollection $routes) => ... (opcional).
    'files' => [],
];
