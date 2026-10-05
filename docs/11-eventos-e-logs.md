# Eventos e logs

## Eventos (PSR-14)

Qualquer objeto é um evento:

```php
final readonly class OrderPaid
{
    public function __construct(public Order $order) {}
}

$dispatcher->dispatch(new OrderPaid($order));   // EventDispatcherInterface injetado
```

Ouvintes são registrados em `config/events.php` (por classe, resolvidos pelo
container e chamados via `__invoke` ou `handle`) ou diretamente no
`ListenerProvider`:

```php
'listeners' => [
    OrderPaid::class => [SendReceipt::class, NotifyWarehouse::class],
],
```

```php
$provider->listen(OrderPaid::class, fn (OrderPaid $event) => ..., priority: 10);
```

Ouvintes de uma interface ou classe-mãe recebem também os eventos que a
implementam. Maior prioridade executa antes. Estenda `StoppableEvent` para
permitir `stopPropagation()`.

Eventos do framework:

| Evento | Quando |
| --- | --- |
| `Forja\Http\Event\RequestReceived` | início do atendimento (`request`) |
| `Forja\Http\Event\ResponseSent` | após emitir a resposta (`request`, `response`, `durationMs`) |
| `Forja\Database\Event\QueryExecuted` | após cada consulta (`sql`, `bindings`, `timeMs`, `driver`) |

## Logs (PSR-3)

Injete `Psr\Log\LoggerInterface`:

```php
$logger->info('Pedido {id} pago por {email}', ['id' => 42, 'email' => 'ada@site.com']);
$logger->error('Falha no gateway', ['exception' => $e]);
```

O logger padrão grava uma linha por registro em `logging.path` (padrão
`var/logs/<canal>.log`), a partir do nível `logging.level`:

```
[2026-10-05T12:00:00-03:00] app.INFO: Pedido 42 pago por ada@site.com {"id":42,"email":"ada@site.com"}
```

Use `php://stderr` como caminho em containers. Para outros destinos,
implemente `Forja\Log\Handler\HandlerInterface` e adicione com
`Logger::pushHandler()`, ou registre outro `LoggerInterface` (como o
Monolog) no container.
