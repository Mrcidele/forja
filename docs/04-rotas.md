# Rotas e controllers

## Rotas por atributos

```php
use Forja\Routing\Attribute\Group;
use Forja\Routing\Attribute\Route;

#[Group(prefix: '/users', name: 'users.')]
final readonly class UserController
{
    public function __construct(private UserRepository $users) {}

    #[Route('/', name: 'index')]
    public function index(): array { ... }

    #[Route('/{id:int}', name: 'show')]
    public function show(int $id, ServerRequestInterface $request): array { ... }

    #[Route('/', methods: ['POST'], name: 'store')]
    public function store(#[FromBody] CreateUserData $data): ResponseInterface { ... }
}

#[Route('/posts/{slug:slug}', name: 'posts.show')]   // controller de ação única
final class ShowPost
{
    public function __invoke(string $slug): string { ... }
}
```

Os diretórios em `routing.controllers` são varridos (os arquivos são lidos
por tokens, sem executar). `#[Route]` é repetível e aceita `methods` como
string ou lista.

### Parâmetros

| Sintaxe | Casa com |
| --- | --- |
| `{id}` | qualquer coisa exceto `/` |
| `{id:int}` | dígitos (o valor chega como `int`) |
| `{slug:slug}` | `meu-post-1` |
| `{id:uuid}` | UUID |
| `{name:alpha}` / `{code:alnum}` | letras / letras e números |
| `{path:any}` | qualquer coisa, inclusive `/` |
| `{lang:[a-z]{2}}` | regex própria (use grupos não capturantes `(?:...)`) |

No controller, o valor é convertido para o tipo do parâmetro: `int`,
`float`, `bool`, `string` ou um `BackedEnum` (`Status $status` recebe
`Status::from(...)`). Valores que não convertem viram 404.

## Rotas em arquivo

```php
// routes/web.php (listado em routing.files)
return static function (RouteCollection $routes): void {
    $routes->get('/ping', fn () => 'pong', 'ping');
    $routes->group('/admin', function (RouteCollection $routes): void {
        $routes->get('/dashboard', [DashboardController::class, 'index']);
    }, name: 'admin.', middleware: [AuthMiddleware::class]);
};
```

Verbos disponíveis: `get`, `post`, `put`, `patch`, `delete`, `options`,
`any` e `add($methods, ...)`. Requisições `HEAD` usam as rotas `GET`.

## Injeção no controller

O controller é resolvido pelo container (dependências no construtor) e o
método recebe por autowiring:

- parâmetros da rota, pelo nome;
- `ServerRequestInterface` e a `Route` atual;
- atributos da requisição registrados pelo nome da classe (ex.: `Session`);
- DTOs com `#[FromBody]`/`#[FromQuery]` ([validação](08-validacao.md));
- qualquer serviço do container.

## Retorno

| Retorno | Resposta |
| --- | --- |
| `ResponseInterface` | enviada como está |
| `string`/`Stringable` | HTML 200 |
| `array`, `JsonSerializable`, escalares | JSON 200 |
| `null` | 204 |

`Forja\Http\ResponseFactory` tem atalhos: `json()`, `html()`, `text()`,
`redirect()`, `noContent()`.

## URLs e 404/405

```php
$router->url('users.show', ['id' => 5, 'tab' => 'posts']); // /users/5?tab=posts
```

Sem rota casada, o `RouteHandler` lança `NotFoundHttpException`; com o
caminho existente mas outro método, `MethodNotAllowedHttpException` (com o
header `Allow`).

## Como o casamento funciona

Rotas estáticas ficam num mapa por método. As dinâmicas são compiladas em
poucas regexes combinadas por método, no formato
`~^(?|/users/(\d+)(*:0)|/posts/([^/]+)(*:1))$~`: um `preg_match` testa o
bloco inteiro e o marcador `(*:N)` indica a rota.

`php forja route:cache` grava o mapa compilado em `var/cache/routes.php`
(rotas com closure não podem ir para o cache). `php forja route:list` lista
as rotas.
