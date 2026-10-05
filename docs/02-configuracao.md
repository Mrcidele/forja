# Configuração e ambiente

## .env

Variáveis do `.env` são carregadas com `vlucas/phpdotenv` sem sobrescrever
as que já existem no ambiente real. Leia-as com `Forja\Config\Env`:

```php
use Forja\Config\Env;

Env::get('APP_DEBUG');            // "true" vira true, "null" vira null, "empty" vira ""
Env::string('APP_NAME', 'Forja');
Env::bool('SESSION_SECURE', false);
Env::int('DB_PORT', 3306);
```

Use `Env` apenas dentro de `config/*.php`: com a configuração em cache o
`.env` não é lido.

## Arquivos de configuração

Cada arquivo em `config/` retorna um array e vira uma chave:

```php
// config/services.php
return [
    'mail' => ['host' => Env::string('MAIL_HOST', 'localhost'), 'port' => 587],
];
```

Acesse por notação de ponto com `Forja\Config\Config` (injetável):

```php
$config->get('services.mail.host');
$config->get('services.mail.user', 'padrão');
$config->has('services.mail');
$config->set('services.mail.port', 2525);

// getters tipados lançam exceção se o tipo não conferir
$config->string('app.name');
$config->int('services.mail.port');
$config->bool('app.debug');
$config->array('http.middleware');
```

## Ambiente

`app.env` vira o enum `Forja\Config\Environment`:

```php
use Forja\Config\Environment;

Environment::fromName('prod');     // Environment::Production (aceita prod, dev, local, test)
$environment->isProduction();
$environment->debugByDefault();    // true em Development e Testing
```

`Forja\Config\AppConfig` é a configuração principal tipada (readonly) e pode
ser injetada:

```php
final readonly class AppConfig
{
    public string $name;
    public Environment $environment;
    public bool $debug;         // app.debug ou o padrão do ambiente
    public string $url;
    public string $timezone;
}
```

Os demais objetos de configuração do framework também são readonly e são
montados a partir dos arquivos: `SessionOptions` (`config/session.php`),
`CorsOptions` (`config/cors.php`) e `DatabaseConfig` (`config/database.php`).

## Chaves usadas pelo framework

| Chave | Uso |
| --- | --- |
| `app.name`, `app.env`, `app.debug`, `app.url`, `app.timezone` | `AppConfig` |
| `app.providers` | service providers da aplicação |
| `app.namespace` | namespace usado pelos geradores `make:*` (padrão `App\`) |
| `http.middleware` | middlewares globais |
| `http.api_prefixes` | caminhos que sempre recebem erros em JSON |
| `http.csrf_except` | caminhos ignorados pelo CSRF |
| `http.rate_limit.max_attempts` / `decay_seconds` | `RateLimitMiddleware` |
| `routing.controllers` / `routing.files` | fontes de rotas |
| `database.default`, `database.connections.*`, `database.migrations` | banco |
| `session.*`, `cors.*` | sessão e CORS |
| `logging.channel`, `logging.path`, `logging.level` | logger |
| `events.listeners` | ouvintes de eventos |
| `view.paths` | diretórios de templates |
| `console.commands` | comandos próprios |

## Cache

`php forja config:cache` grava toda a configuração resolvida em
`var/cache/config.php`; `php forja config:cache --clear` remove o arquivo.
