# Changelog

Todas as mudanças relevantes deste projeto são registradas aqui.

O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/)
e o projeto adota o [Versionamento Semântico](https://semver.org/lang/pt-BR/).
Enquanto a versão principal for 0, mudanças incompatíveis podem acontecer em
versões menores (0.x).

## [Não lançado]

## [0.1.0] - 2026-10-05

Primeira versão pública.

### Adicionado

- **HTTP (PSR-7/PSR-17):** `Kernel`, `RequestFactory` (superglobais via
  nyholm/psr7-server), `SapiEmitter` com corpo em blocos e `ResponseFactory`.
- **Container (PSR-11):** autowiring por reflection, detecção de dependência
  circular, `bind`/`singleton`/`factory`/`instance`, `make()`, `call()`,
  service providers (`register`/`boot`) e container compilado para produção.
- **Rotas:** atributos `#[Route]`, `#[Group]` e `#[Middleware]`, scanner de
  controllers, regex compilada por método, parâmetros tipados (`{id:int}`) ou
  com regex, grupos, rotas nomeadas, geração de URL, cache do mapa e
  conversão de parâmetros para o tipo declarado (incluindo enums).
- **Middleware (PSR-15):** pipeline com middlewares globais e por
  rota/grupo; tratamento de erros, CORS, rate limit, sessão e CSRF.
- **Erros:** `ExceptionHandler` com JSON para APIs, página de depuração em
  desenvolvimento e página genérica em produção; `ErrorHandler` global e
  exceções HTTP tipadas (400, 401, 403, 404, 405, 409, 410, 422, 429, 503).
- **Configuração:** `.env` (vlucas/phpdotenv), `config/*.php` com notação de
  ponto, enum `Environment`, `AppConfig` readonly e cache de configuração.
- **Banco de dados:** `Connection` sobre PDO com transações aninhadas, query
  builder fluente com paginação, schema builder para SQLite/MySQL/PostgreSQL,
  migrations com lotes e ORM Data Mapper com atributos e relacionamentos.
- **CLI:** binário `forja` (symfony/console) com `migrate`,
  `migrate:rollback`, `migrate:status`, `migrate:reset`, `make:*` a partir
  de stubs, `route:list`, `route:cache`, `config:cache`, `cache:clear` e
  `optimize`.
- **Eventos e logs:** dispatcher PSR-14 e logger PSR-3 próprios; eventos
  `RequestReceived`, `ResponseSent` e `QueryExecuted`.
- **Views e validação:** engine de templates com compilação e cache, layouts,
  seções e includes; JSON resources; validação por atributos em DTOs com
  hydration do corpo/query (`#[FromBody]`, `#[FromQuery]`).
- **Performance:** script de preload do OPcache e worker mode do FrankenPHP
  com limpeza de estado entre requisições.
- **Publicação:** skeleton `forja/app`, app de demonstração (API de tarefas),
  documentação e CI para o framework, o skeleton e a demo.

[Não lançado]: https://github.com/Mrcidele/forja/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Mrcidele/forja/releases/tag/v0.1.0
