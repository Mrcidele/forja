# Forja Tarefas (app de demonstração)

API de tarefas com um quadro web, construída sobre o skeleton do Forja para
mostrar os componentes trabalhando juntos.

| Recurso | Onde |
| --- | --- |
| Rotas por atributos, grupos, nomes e middleware por grupo | `app/Http/Controllers` |
| DTOs validados com `#[FromBody]` e `#[FromQuery]` | `app/Http/Data` |
| JSON resources e paginação | `app/Http/Resources` |
| ORM Data Mapper com `HasMany`/`BelongsTo` e repositório | `app/Entity`, `app/Repositories` |
| Migrations | `database/migrations` |
| Evento + ouvinte que grava no log | `app/Events`, `app/Listeners`, `config/events.php` |
| View com layout, `@foreach`, `@csrf` e mensagem flash | `resources/views` |
| CORS, sessão, CSRF e rate limit | `config/http.php` |

## Rodando

O `composer.json` aponta `forja/framework` para o código deste repositório
(`../..`).

```bash
composer install
cp .env.example .env
php forja migrate
composer serve             # http://localhost:8000
composer test
```

## API

| Método | Caminho | Descrição |
| --- | --- | --- |
| `GET` | `/api/projects` | projetos com as tarefas |
| `POST` | `/api/projects` | `{"name": "Forja"}` |
| `GET` | `/api/tasks?status=pendente&projectId=1&page=1&perPage=20` | lista paginada |
| `POST` | `/api/tasks` | `{"title": "...", "projectId": 1, "status": "pendente", "dueDate": "2026-12-01"}` |
| `GET` | `/api/tasks/{id}` | detalhe |
| `PATCH` | `/api/tasks/{id}` | atualização parcial; concluir dispara `TaskCompleted` |
| `DELETE` | `/api/tasks/{id}` | remove (204) |

```bash
curl -X POST localhost:8000/api/projects -H 'Content-Type: application/json' -d '{"name":"Forja"}'
curl -X POST localhost:8000/api/tasks -H 'Content-Type: application/json' -d '{"title":"Escrever docs","projectId":1}'
curl -X PATCH localhost:8000/api/tasks/1 -H 'Content-Type: application/json' -d '{"status":"concluida"}'
```
