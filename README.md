# Forja

[![CI](https://github.com/Mrcidele/forja/actions/workflows/ci.yml/badge.svg)](https://github.com/Mrcidele/forja/actions/workflows/ci.yml)

Framework PHP 8.4 construído do zero sobre as PSRs. Os componentes centrais
(container, router, pipeline de middleware, eventos, logger, ORM, templates)
são implementações próprias, mas seguem as interfaces padronizadas para
serem interoperáveis com o ecossistema.

```php
#[Group(prefix: '/api/tasks', name: 'tasks.', middleware: [RateLimitMiddleware::class])]
final readonly class TaskController
{
    public function __construct(private TaskRepository $tasks) {}

    #[Route('/{id:int}', name: 'show')]
    public function show(int $id): TaskResource
    {
        return TaskResource::make($this->tasks->find($id) ?? throw new NotFoundHttpException());
    }

    #[Route('/', methods: ['POST'], name: 'store')]
    public function store(#[FromBody] CreateTaskData $data): ResponseInterface
    {
        $task = new Task($data->projectId, $data->title);
        $this->tasks->save($task);

        return TaskResource::make($task)->response(201);
    }
}
```

## Recursos

| Área | O que tem | PSR |
| --- | --- | --- |
| HTTP | `Kernel`, criação da requisição a partir das superglobais, emitter com corpo em blocos | 7, 17 |
| Container | autowiring, detecção de ciclos, singletons, fábricas, providers, container compilado | 11 |
| Rotas | atributos, regex compilada, parâmetros tipados, grupos, nomes, cache, conversão para o tipo declarado | — |
| Middleware | pipeline global e por rota; erros, CORS, rate limit, sessão e CSRF | 15 |
| Erros | JSON para API, página de depuração em dev, genérica em produção, exceções HTTP tipadas | — |
| Configuração | `.env`, `config/*.php` com notação de ponto, enum de ambiente, configs readonly | — |
| Banco | PDO com transações aninhadas, query builder, schema builder, migrations, ORM Data Mapper | — |
| CLI | binário `forja` com migrations, geradores por stubs, rotas e caches | — |
| Eventos e logs | dispatcher e logger próprios, eventos do ciclo de vida | 14, 3 |
| Views e validação | templates compilados com cache, JSON resources, DTOs validados por atributos | — |
| Performance | preload do OPcache e worker mode do FrankenPHP | — |
| Cache | `ArrayCache` e `FileCache` | 16 |

## Começando

```bash
composer create-project forja/app minha-app
cd minha-app
composer serve
```

A [documentação](docs/README.md) cobre cada componente com exemplos. A
[app de demonstração](examples/tasks) é uma API de tarefas com quadro web que
usa rotas, DTOs validados, ORM com relacionamentos, migrations, resources,
eventos, logs, sessão, CSRF, CORS e rate limit.

## Este repositório

| Caminho | Conteúdo |
| --- | --- |
| `src/` | o framework (`forja/framework`) |
| `stubs/` | modelos usados pelos comandos `make:*` |
| `bin/forja` | binário de linha de comando |
| `skeleton/` | estrutura inicial de aplicação (`forja/app`), pronta para virar um repositório próprio |
| `examples/tasks/` | app de demonstração |
| `docs/` | documentação |
| `tests/` | testes do framework |

## Desenvolvimento

Requisitos: PHP 8.4+ e Composer 2.

```bash
composer install

composer test            # Pest
composer analyse         # PHPStan nível max
composer lint            # Pint (PSR-12) em modo verificação
composer format          # Pint aplicando correções
composer refactor:check  # Rector em dry-run
composer check           # tudo acima, na ordem da CI
```

A CI roda as verificações acima e os testes do skeleton e da app demo contra
o código do framework em cada push. O histórico de versões está no
[CHANGELOG](CHANGELOG.md).

## Licença

[MIT](LICENSE)
