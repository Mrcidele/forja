<?php

declare(strict_types=1);

namespace Forja\Container;

/**
 * Serviços compartilhados que guardam estado de uma requisição e precisam
 * ser limpos entre requisições em worker mode.
 */
interface ResettableInterface
{
    public function reset(): void;
}
