<?php

declare(strict_types=1);

use Forja\Database\Connection;
use Forja\Events\ListenerProvider;
use Forja\Foundation\Application;
use Forja\Http\Emitter\EmitterInterface;
use Forja\Http\Event\RequestReceived;
use Forja\Http\Event\ResponseSent;
use Forja\Tests\Fixtures\Events\RecordQueries;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;

beforeEach(function (): void {
    $this->basePath = copyFixtureApp();
    file_put_contents($this->basePath . '/config/events.php', '<?php return ["listeners" => [Forja\Database\Event\QueryExecuted::class => [Forja\Tests\Fixtures\Events\RecordQueries::class]]];');
    $this->app = Application::create($this->basePath);
    RecordQueries::$queries = [];
});

afterEach(function (): void {
    removeDirectory($this->basePath);
    date_default_timezone_set('UTC');
});

it('dispara eventos do ciclo de vida da requisição', function (): void {
    $events = [];
    $provider = $this->app->container->get(ListenerProvider::class);
    $provider->listen(RequestReceived::class, function (RequestReceived $event) use (&$events): void {
        $events[] = 'recebida ' . $event->request->getUri()->getPath();
    });
    $provider->listen(ResponseSent::class, function (ResponseSent $event) use (&$events): void {
        $events[] = 'enviada ' . $event->response->getStatusCode() . ($event->durationMs >= 0 ? ' com duração' : '');
    });
    $this->app->container->instance(EmitterInterface::class, new class () implements EmitterInterface {
        public function emit(ResponseInterface $response): void
        {
        }
    });

    $this->app->send(new ServerRequest('GET', '/'));

    expect($events)->toBe(['recebida /', 'enviada 200 com duração']);
});

it('dispara QueryExecuted para os ouvintes configurados', function (): void {
    $this->app->container->get(Connection::class)->select('select 1 as um');

    expect(RecordQueries::$queries)->toBe(['select 1 as um']);
});

it('registra no log as exceções do servidor, mas não as do cliente', function (): void {
    $this->app->handle(new ServerRequest('GET', '/api/boom'));
    $this->app->handle(new ServerRequest('GET', '/rota-inexistente'));

    $log = (string) file_get_contents($this->basePath . '/var/logs/forja.log');

    expect($log)->toContain('forja-fixture.ERROR: explodiu')->toContain('"class":"RuntimeException"')
        ->and($log)->not->toContain('Nenhuma rota');
});
