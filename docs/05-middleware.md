# Middlewares

Middlewares seguem a PSR-15 (`MiddlewareInterface`):

```php
final class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getHeaderLine('Authorization') === '') {
            throw new UnauthorizedHttpException('Bearer');
        }

        return $handler->handle($request->withAttribute('user', ...));
    }
}
```

`php forja make:middleware Auth` gera o esqueleto.

## Globais, por grupo e por rota

```php
// config/http.php — do mais externo ao mais interno
'middleware' => [CorsMiddleware::class, ErrorHandlerMiddleware::class, SessionMiddleware::class, CsrfMiddleware::class],
```

```php
#[Group('/admin', middleware: [AuthMiddleware::class])]
#[Middleware(AuditMiddleware::class)]
final class AdminController
{
    #[Route('/reports', middleware: [ThrottleReports::class])]
    #[Middleware(CacheMiddleware::class)]
    public function reports(): array { ... }
}
```

A ordem na rota é: grupo, `#[Middleware]` da classe, `#[Middleware]` do
método e o parâmetro `middleware` do `#[Route]`. Middlewares informados por
classe são resolvidos pelo container apenas quando a execução chega neles.

O `Pipeline` pode ser usado diretamente:

```php
new Pipeline([First::class, new Second()], $handlerFinal, $container)->handle($request);
```

## Middlewares inclusos

| Middleware | Função |
| --- | --- |
| `ErrorHandlerMiddleware` | converte exceções (e erros do PHP) em resposta via `ExceptionHandlerInterface` |
| `CorsMiddleware` | responde preflights e adiciona headers de CORS (`config/cors.php`); origens aceitam curinga (`https://*.site.com`) |
| `RateLimitMiddleware` | janela fixa por IP (ou chave própria), headers `X-RateLimit-*` e 429 com `Retry-After` |
| `SessionMiddleware` | sessão própria (sem `session_*`), cookie HttpOnly/SameSite, flash e proteção contra fixação |
| `CsrfMiddleware` | token da sessão via campo `_token` ou header `X-CSRF-Token`; exclusões em `http.csrf_except` |

### Sessão

```php
public function login(Session $session): ResponseInterface
{
    $session->regenerate();            // novo ID após autenticar
    $session->set('user_id', 7);
    $session->flash('status', 'Bem-vindo!'); // disponível só na próxima requisição
    ...
}

$session->get('user_id');
$session->pull('status');
$session->invalidate();               // logout
```

As sessões são guardadas em `var/sessions` por um cache PSR-16
(`CacheSessionStore`); troque `SessionStoreInterface` no container para usar
outro armazenamento.

### CSRF em formulários

O token fica no atributo `csrf_token` da requisição. Passe-o para a view e
use `@csrf` dentro do `<form>`.

### Rate limit

O limitador usa qualquer `Psr\SimpleCache\CacheInterface` (o padrão é
`FileCache` em `var/cache/data`). Para limites diferentes por rota, crie uma
subclasse com outros parâmetros ou registre outra instância no container.
