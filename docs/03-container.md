# Container de injeção de dependências

`Forja\Container\Container` implementa a PSR-11 (`get`/`has`) e resolve
classes concretas por autowiring do construtor, mesmo sem registro.

```php
final class ReportService
{
    public function __construct(private Connection $db, private LoggerInterface $logger) {}
}

$service = $container->get(ReportService::class); // dependências resolvidas recursivamente
```

Regras do autowiring, para cada parâmetro do construtor:

1. tipo de classe/interface que o container resolve → injeta;
2. senão, valor padrão → usa;
3. senão, tipo nullable → `null`;
4. senão → `ContainerException` com o parâmetro e a classe.

Dependências circulares lançam `CircularDependencyException` com a cadeia
(`A -> B -> A`).

## Bindings

```php
$container->bind(MailerInterface::class, SmtpMailer::class);        // nova instância a cada get()
$container->singleton(Connection::class, fn (Config $c) => new Connection(...)); // uma instância
$container->singleton(Router::class);                               // singleton por autowiring
$container->factory(Request::class, fn () => ...);                  // sempre executa a fábrica
$container->instance('config.extra', ['x' => 1]);                   // valor pronto
```

Fábricas recebem dependências por tipo (o próprio container inclusive).

```php
$container->make(Mailer::class, ['from' => 'eu@site.com']);   // sempre nova instância, com parâmetros
$container->call([new Greeter(), 'greet'], ['name' => 'Caio']); // injeção em métodos e closures
```

## Service providers

```php
final class BillingServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(Gateway::class, StripeGateway::class);
    }

    // opcional, chamado depois que todos os providers foram registrados;
    // recebe dependências por autowiring
    public function boot(Router $router): void
    {
    }
}
```

Registre em `config/app.php` (`providers`).

## Container compilado

Em produção, `php forja optimize` gera `var/cache/container.php` com fábricas
para os bindings, controllers e middlewares, sem reflection. Classes que não
podem ser compiladas (parâmetros escalares obrigatórios, objetos como valor
padrão) continuam usando reflection.

```php
new ContainerCompiler()->dump([UserController::class, Mailer::class], $arquivo);
$container->loadCompiled(ContainerCompiler::load($arquivo));
```
