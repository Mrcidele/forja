<?php

declare(strict_types=1);

use Forja\Routing\RouteCollection;

return static function (RouteCollection $routes): void {
    $routes->get('/closure', static fn (): string => 'rota em closure');
};
