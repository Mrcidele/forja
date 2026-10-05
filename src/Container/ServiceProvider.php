<?php

declare(strict_types=1);

namespace Forja\Container;

/**
 * Agrupa o registro de serviços de um módulo.
 *
 * register() só deve registrar bindings. Para inicializações que dependem de
 * outros serviços, declare um método boot(), chamado depois que todos os
 * providers foram registrados e que recebe dependências por autowiring.
 */
abstract class ServiceProvider
{
    abstract public function register(Container $container): void;
}
