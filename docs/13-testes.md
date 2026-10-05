# Testes

`Application::handle()` atende um `ServerRequestInterface` sem servidor nem
emitter, o que permite testes de ponta a ponta rápidos (exemplos com Pest):

```php
use Forja\Database\Migrations\Migrator;
use Forja\Foundation\Application;
use Nyholm\Psr7\ServerRequest;

function app(): Application
{
    $app = Application::create(dirname(__DIR__));
    $app->container->get(Migrator::class)->migrate();   // banco em memória (DB_DATABASE=:memory:)

    return $app;
}

it('cria tarefas', function (): void {
    $response = app()->handle(
        new ServerRequest('POST', '/api/tasks', ['Content-Type' => 'application/json'], '{"title":"Docs","projectId":1}'),
    );

    expect($response->getStatusCode())->toBe(201);
});
```

No `phpunit.xml`, aponte o ambiente para testes:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="DB_DATABASE" value=":memory:"/>
</php>
```

Dicas:

- substitua serviços no container antes da requisição:
  `$app->container->instance(MailerInterface::class, new FakeMailer());`;
- troque o `EmitterInterface` por um fake para testar `Application::send()`;
- comandos podem ser testados com o `CommandTester` do symfony/console sobre
  `new Forja\Console\Kernel($app)->console()`.

A [app de demonstração](../examples/tasks/tests) tem testes completos de API
e de formulários com sessão e CSRF.
