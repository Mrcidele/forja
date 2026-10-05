# Tratamento de erros

O `ErrorHandlerMiddleware` (primeiro da lista em `http.middleware`) captura
qualquer exceção e erros do PHP convertidos em `ErrorException`. O
`ErrorHandler` global (registrado por `Application::run()`) cobre o que
escapa do pipeline, inclusive erros fatais no shutdown.

## Formato da resposta

O `ExceptionHandler` escolhe pelo contexto:

| Situação | Resposta |
| --- | --- |
| `Accept` com JSON ou caminho em `http.api_prefixes` | `{"error": {"status": 404, "message": "..."}}` |
| `app.debug` ativo | página de depuração: mensagem, trecho do código, stack trace das exceções encadeadas e dados da requisição |
| produção | página genérica com status e mensagem, sem detalhes internos |

Em debug, o JSON também traz `exception`, `file`, `line` e `trace`. Erros de
validação incluem `errors` com as mensagens por campo.

Falhas do servidor (exceções genéricas e `HttpException` com status ≥ 500)
são registradas no log; erros do cliente (4xx) não.

## Exceções HTTP

Lance-as de qualquer ponto (controller, middleware, serviço):

```php
throw new NotFoundHttpException('Pedido não encontrado.');
throw new UnauthorizedHttpException('Bearer realm="api"');
throw new TooManyRequestsHttpException(retryAfter: 30);
throw new UnprocessableEntityHttpException(['email' => ['Já cadastrado.']]);
throw new HttpException(418, 'Sou um bule.', ['X-Teapot' => 'true']);
```

| Classe | Status |
| --- | --- |
| `BadRequestHttpException` | 400 |
| `UnauthorizedHttpException` | 401 (`WWW-Authenticate`) |
| `ForbiddenHttpException` | 403 |
| `NotFoundHttpException` | 404 |
| `MethodNotAllowedHttpException` | 405 (`Allow`) |
| `ConflictHttpException` | 409 |
| `GoneHttpException` | 410 |
| `UnprocessableEntityHttpException` / `ValidationException` | 422 |
| `TooManyRequestsHttpException` | 429 (`Retry-After`) |
| `ServiceUnavailableHttpException` | 503 |

## Personalizando

Estenda `ExceptionHandler` (ou implemente `ExceptionHandlerInterface`) e
registre no container:

```php
$container->singleton(ExceptionHandlerInterface::class, MeuHandler::class);
```
