# Aplicação Forja

Estrutura inicial para aplicações com o [Forja](https://github.com/Mrcidele/forja).

```bash
composer create-project forja/app minha-app
cd minha-app
composer serve              # http://localhost:8000
php forja list              # comandos disponíveis
php forja make:controller Post
php forja migrate
composer test
```

## Estrutura

| Caminho | Conteúdo |
| --- | --- |
| `app/Http/Controllers` | Controllers com rotas por atributos |
| `app/Providers` | Service providers da aplicação |
| `bootstrap/app.php` | Cria a `Application` (usado por web, worker, console e testes) |
| `config/` | Configuração com acesso por notação de ponto |
| `database/migrations` | Migrations versionadas |
| `public/index.php` | Entrada HTTP (PHP-FPM, servidor embutido) |
| `public/worker.php` | Entrada do worker mode do FrankenPHP |
| `resources/views` | Templates `.forja.php` |
| `var/` | Caches, sessões, logs e banco SQLite |
| `deploy/` | Exemplos de Caddyfile (FrankenPHP) e php.ini com OPcache/preload |

## Produção

```bash
composer install --no-dev --optimize-autoloader
php forja optimize          # caches de configuração, rotas, container e preload
php forja migrate
```
