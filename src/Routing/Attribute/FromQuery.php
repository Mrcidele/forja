<?php

declare(strict_types=1);

namespace Forja\Routing\Attribute;

use Attribute;

/**
 * Preenche o parâmetro (um DTO) com a query string, validada.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class FromQuery
{
}
