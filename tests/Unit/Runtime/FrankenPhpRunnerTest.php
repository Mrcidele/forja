<?php

declare(strict_types=1);

use Forja\Container\ResettableInterface;
use Forja\Foundation\Application;
use Forja\Http\Emitter\EmitterInterface;
use Forja\Runtime\FrankenPhpRunner;
use Psr\Http\Message\ResponseInterface;

beforeEach(function (): void {
    $this->basePath = copyFixtureApp();
    $this->app = Application::create($this->basePath);
    $this->emitted = new ArrayObject();
    $this->app->container->instance(EmitterInterface::class, new readonly class ($this->emitted) implements EmitterInterface {
        /** @param ArrayObject<int, string> $emitted */
        public function __construct(private ArrayObject $emitted)
        {
        }

        public function emit(ResponseInterface $response): void
        {
            $this->emitted[] = $response->getStatusCode() . ' ' . $response->getBody();
        }
    });
    $this->backup = $_SERVER;
});

afterEach(function (): void {
    $_SERVER = $this->backup;
    removeDirectory($this->basePath);
    date_default_timezone_set('UTC');
});

/**
 * Simula o FrankenPHP: preenche as superglobais de cada requisição e chama o handler.
 *
 * @param list<string> $paths
 */
function fakeFrankenPhp(array $paths, bool $stopAfterLast = true): Closure
{
    return static function (callable $handler) use (&$paths, $stopAfterLast): bool {
        $path = array_shift($paths);
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path, 'HTTP_HOST' => 'forja.test'];
        $handler();

        return ! $stopAfterLast || $paths !== [];
    };
}

it('atende várias requisições no mesmo processo', function (): void {
    $runner = new FrankenPhpRunner($this->app, handleRequest: fakeFrankenPhp(['/', '/closure', '/nada']));

    expect($runner->run())->toBe(3)
        ->and($this->emitted[0])->toBe('200 Bem-vindo à Forja Fixture')
        ->and($this->emitted[1])->toBe('200 rota em closure')
        ->and($this->emitted[2])->toStartWith('404');
});

it('limpa o estado compartilhado entre requisições', function (): void {
    $service = new class () implements ResettableInterface {
        public int $resets = 0;

        public function reset(): void
        {
            $this->resets++;
        }
    };
    $this->app->container->instance('estado', $service);

    new FrankenPhpRunner($this->app, handleRequest: fakeFrankenPhp(['/', '/']))->run();

    expect($service->resets)->toBe(2);
});

it('converte exceções que escapam do kernel sem derrubar o worker', function (): void {
    file_put_contents($this->basePath . '/config/http.php', "<?php return ['middleware' => []];");
    $app = Application::create($this->basePath);
    $app->container->instance(EmitterInterface::class, $this->app->container->get(EmitterInterface::class));

    expect(new FrankenPhpRunner($app, handleRequest: fakeFrankenPhp(['/nada', '/']))->run())->toBe(2)
        ->and($this->emitted[0])->toStartWith('404')
        ->and($this->emitted[1])->toBe('200 Bem-vindo à Forja Fixture');
});

it('reinicia o worker ao atingir o limite de requisições', function (): void {
    $runner = new FrankenPhpRunner($this->app, maxRequests: 2, handleRequest: fakeFrankenPhp(['/', '/', '/'], stopAfterLast: false));

    expect($runner->run())->toBe(2);
});

it('exige o FrankenPHP quando nenhum handler é informado', function (): void {
    new FrankenPhpRunner($this->app);
})->throws(RuntimeException::class, 'FrankenPHP');

it('desfaz transações esquecidas ao limpar a conexão', function (): void {
    $connection = sqlite();
    $connection->unprepared('create table t (id integer)');
    $connection->beginTransaction();
    $connection->insert('insert into t values (1)');

    $connection->reset();

    expect($connection->transactionLevel())->toBe(0)
        ->and($connection->scalar('select count(*) from t'))->toBe(0)
        ->and($connection)->toBeInstanceOf(ResettableInterface::class);
});
