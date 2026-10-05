<?php

declare(strict_types=1);

namespace Forja\Config;

/**
 * Configuração principal da aplicação (config/app.php), já tipada.
 */
final readonly class AppConfig
{
    public function __construct(
        public string $name = 'Forja',
        public Environment $environment = Environment::Production,
        public bool $debug = false,
        public string $url = 'http://localhost',
        public string $timezone = 'UTC',
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        $environment = $config->get('app.env', Environment::Production->value);
        $environment = $environment instanceof Environment ? $environment : Environment::fromName(is_string($environment) ? $environment : '');
        $debug = $config->get('app.debug');

        return new self(
            name: $config->string('app.name', 'Forja'),
            environment: $environment,
            debug: is_bool($debug) ? $debug : $environment->debugByDefault(),
            url: $config->string('app.url', 'http://localhost'),
            timezone: $config->string('app.timezone', 'UTC'),
        );
    }
}
