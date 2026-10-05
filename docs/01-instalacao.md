# Instalação e estrutura

Requisitos: PHP 8.4+, Composer 2 e as extensões `pdo` (com o driver do seu
banco) e `mbstring`.

## Nova aplicação

```bash
composer create-project forja/app minha-app
cd minha-app
composer serve        # servidor embutido em http://localhost:8000
```

O `create-project` copia `.env.example` para `.env`. Para usar só o núcleo em
um projeto existente:

```bash
composer require forja/framework
```

## Estrutura do skeleton

```
app/
  Http/Controllers/   controllers com rotas por atributos
  Providers/          service providers da aplicação
bootstrap/app.php     cria a Application (web, worker, console e testes)
config/               um arquivo por área (app, http, database...)
database/migrations/  migrations versionadas
public/index.php      entrada HTTP
public/worker.php     entrada do worker mode (FrankenPHP)
resources/views/      templates .forja.php
var/                  caches, sessões, logs e o banco SQLite
forja                 linha de comando
```

## Ciclo de uma requisição

```
public/index.php
  └─ Application::run()
       ├─ RequestFactory::fromGlobals()        ServerRequestInterface (PSR-7)
       ├─ Kernel::handle()
       │    ├─ middlewares globais (PSR-15)    erros, sessão, CSRF...
       │    └─ RouteHandler
       │         ├─ Router::match()            404 / 405 / rota encontrada
       │         ├─ middlewares da rota
       │         └─ ControllerInvoker          autowiring + conversão de tipos
       └─ SapiEmitter::emit()                  headers, status e corpo
```

`bootstrap/app.php` é só:

```php
return Forja\Foundation\Application::create(dirname(__DIR__));
```

`Application::create()` carrega o `.env` e `config/*.php` (ou o cache deles),
registra o `FrameworkServiceProvider` e os providers de `app.providers` e
executa o `boot()` de todos.
