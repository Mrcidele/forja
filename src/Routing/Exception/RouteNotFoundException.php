<?php

declare(strict_types=1);

namespace Forja\Routing\Exception;

use InvalidArgumentException;

/**
 * Lançada ao gerar URL para um nome de rota inexistente ou com parâmetros inválidos.
 */
final class RouteNotFoundException extends InvalidArgumentException
{
}
