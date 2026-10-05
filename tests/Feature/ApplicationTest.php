<?php

declare(strict_types=1);

use Forja\Config\AppConfig;
use Forja\Config\Config;
use Forja\Config\ConfigLoader;
use Forja\Config\Environment;
use Forja\Foundation\Application;
use Forja\Routing\RouteCache;
use Forja\Routing\Router;
use Forja\Tests\Fixtures\App\FixtureProvider;
use Nyholm\Psr7\ServerRequest;

beforeEach(function (): void {
    $this->basePath = copyFixtureApp();
});

afterEach(function (): void {
    removeDirectory($this->basePath);
    date_default_timezone_set('UTC');
});

it('carrega .env e configuração com notação de ponto', function (): void {
    $app = Application::create($this->basePath);

    expect($app->config()->get('app.name'))->toBe('Forja Fixture')
        ->and($app->config()->get('services.secret'))->toBe('segredo-do-env')
        ->and($app->config()->get('services.flag'))->toBeTrue()
        ->and($app->config()->get('services.mail.port'))->toBe(587)
        ->and($app->environment())->toBe(Environment::Testing)
        ->and($app->container->get(AppConfig::class)->debug)->toBeTrue()
        ->and(date_default_timezone_get())->toBe('America/Sao_Paulo');
});

it('registra e inicializa os providers da configuração', function (): void {
    $boots = FixtureProvider::$boots;
    $app = Application::create($this->basePath);

    expect($app->container->get('fixture.registered'))->toBeTrue()
        ->and(FixtureProvider::$boots)->toBe($boots + 1);
});

it('atende requisições com rotas de atributos e de arquivos', function (): void {
    $app = Application::create($this->basePath);

    expect((string) $app->handle(new ServerRequest('GET', '/'))->getBody())->toBe('Bem-vindo à Forja Fixture')
        ->and((string) $app->handle(new ServerRequest('GET', '/closure'))->getBody())->toBe('rota em closure');
});

it('converte exceções conforme a configuração', function (): void {
    $app = Application::create($this->basePath);

    $notFound = $app->handle(new ServerRequest('GET', '/nada'));
    $apiError = $app->handle(new ServerRequest('GET', '/api/boom'));

    expect($notFound->getStatusCode())->toBe(404)
        ->and($apiError->getStatusCode())->toBe(500)
        ->and(json_decode((string) $apiError->getBody(), true)['error']['message'])->toBe('explodiu');
});

it('usa o cache de configuração sem ler o .env', function (): void {
    new ConfigLoader()->dump(['app' => ['name' => 'Do cache', 'env' => 'production']], $this->basePath . '/var/cache/config.php');

    $app = Application::create($this->basePath);

    expect($app->config()->get('app.name'))->toBe('Do cache')
        ->and($app->config()->has('services'))->toBeFalse()
        ->and($app->environment())->toBe(Environment::Production);
});

it('usa o cache de rotas quando existe', function (): void {
    $router = new Router();
    $router->routes()->get('/do-cache', 'App\CachedController', 'cached');
    RouteCache::dump($router->compiled(), $this->basePath . '/var/cache/routes.php');

    $app = Application::create($this->basePath);

    expect($app->container->get(Router::class)->url('cached'))->toBe('/do-cache')
        ->and($app->container->get(Router::class)->matchPath('GET', '/')->isFound())->toBeFalse();
});

it('expõe os caminhos da aplicação', function (): void {
    $app = new Application('/srv/app/');

    expect($app->basePath('public'))->toBe('/srv/app/public')
        ->and($app->configPath('app.php'))->toBe('/srv/app/config/app.php')
        ->and($app->storagePath('logs'))->toBe('/srv/app/var/logs')
        ->and($app->cachePath())->toBe('/srv/app/var/cache');
});

it('rejeita middlewares e providers inválidos na configuração', function (string $file, string $content, string $message): void {
    file_put_contents($this->basePath . '/config/' . $file, $content);

    expect(fn (): \Psr\Http\Message\ResponseInterface => Application::create($this->basePath)->handle(new ServerRequest('GET', '/')))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'middleware' => ['http.php', "<?php return ['middleware' => ['App\\\\Inexistente']];", 'Middleware inválido'],
    'provider' => ['app.php', "<?php return ['providers' => ['App\\\\Inexistente']];", 'Service provider inválido'],
]);

it('disponibiliza a configuração pelo container', function (): void {
    $app = Application::create($this->basePath);

    expect($app->container->get(Config::class))->toBe($app->config());
});
