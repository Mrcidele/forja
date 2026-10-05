# Produção: caches, OPcache e FrankenPHP

## Deploy

```bash
composer install --no-dev --optimize-autoloader --classmap-authoritative
php forja optimize
php forja migrate
```

`optimize` gera em `var/cache`:

| Arquivo | Conteúdo |
| --- | --- |
| `config.php` | configuração resolvida (o `.env` deixa de ser lido) |
| `routes.php` | mapa de rotas compilado |
| `container.php` | fábricas do container sem reflection |
| `preload.php` | script de preload do OPcache |

Rode `php forja cache:clear` (ou `optimize` de novo) após mudar
configuração, rotas ou dependências.

## OPcache com preload

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.preload=/app/var/cache/preload.php
opcache.preload_user=www-data
```

O preload carrega as classes do framework e de `app/` pelo autoloader do
Composer quando o PHP inicia, deixando-as em memória compartilhada para
todas as requisições. Com `validate_timestamps=0`, reinicie o PHP a cada
deploy. O skeleton traz o exemplo em `deploy/php.ini`.

## Worker mode com FrankenPHP

No worker mode a aplicação sobe uma vez por worker e atende várias
requisições, eliminando o custo de inicialização:

```php
// public/worker.php
$app = require __DIR__ . '/../bootstrap/app.php';

new Forja\Runtime\FrankenPhpRunner($app, maxRequests: 1000)->run();
```

```
{
	frankenphp {
		worker {
			file /app/public/worker.php
			num 4
		}
	}
}

localhost {
	root * /app/public
	php_server
}
```

`maxRequests` reinicia o worker periodicamente para conter vazamentos de
memória. Exceções que escapem do kernel são convertidas em resposta sem
derrubar o worker.

### Cuidados com estado

Como o processo continua vivo entre requisições:

- não guarde dados de requisição em singletons, propriedades estáticas ou
  variáveis globais;
- serviços compartilhados com estado devem implementar
  `Forja\Container\ResettableInterface`; o runner chama `reset()` em todos
  ao fim de cada requisição (a `Connection` desfaz transações esquecidas);
- objetos de requisição (sessão, usuário autenticado) devem viajar como
  atributos do `ServerRequestInterface`, como faz o `SessionMiddleware`;
- `Engine::share()` é só para dados globais;
- o framework não usa `session_*`, `header()` fora do emitter nem
  superglobais depois de criar a requisição.
