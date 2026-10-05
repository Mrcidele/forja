<?php

declare(strict_types=1);

namespace Forja\Validation\Rule;

interface RuleInterface
{
    /**
     * Mensagem de erro para o campo, ou null se o valor é válido.
     * Valores nulos só são verificados pela regra Required.
     */
    public function validate(string $field, mixed $value): ?string;
}
