<?php

declare(strict_types=1);

use Forja\Console\Kernel;
use Forja\Foundation\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function (): void {
    $this->basePath = copyFixtureApp();
    $this->app = Application::create($this->basePath);
    $this->console = new Kernel($this->app)->console();
});

afterEach(function (): void {
    removeDirectory($this->basePath);
    date_default_timezone_set('UTC');
});

/**
 * Remove a rota em closure da aplicação de exemplo (closures não vão para o cache).
 */
function withoutClosureRoutes(object $test): void
{
    file_put_contents($test->basePath . '/config/routing.php', "<?php return ['controllers' => [dirname(__DIR__) . '/Controllers']];");
    $test->app = Application::create($test->basePath);
    $test->console = new Kernel($test->app)->console();
}

/**
 * @param array<string, mixed> $input
 */
function runCommand(object $test, string $name, array $input = []): CommandTester
{
    $tester = new CommandTester($test->console->find($name));
    $tester->execute($input);

    return $tester;
}

it('registra os comandos do framework', function (): void {
    foreach (['migrate', 'migrate:rollback', 'rollback', 'migrate:status', 'migrate:reset', 'make:controller', 'make:middleware', 'make:migration', 'make:entity', 'route:list', 'route:cache', 'config:cache', 'cache:clear', 'optimize'] as $name) {
        expect($this->console->has($name))->toBeTrue();
    }

    expect($this->console->getName())->toBe('Forja')
        ->and($this->console->getVersion())->toBe(Application::VERSION);
});

it('rejeita comandos inválidos na configuração', function (): void {
    $this->app->config()->set('console.commands', ['App\Inexistente']);

    new Kernel($this->app)->console();
})->throws(InvalidArgumentException::class, 'Comando inválido');

it('executa, lista e desfaz migrations', function (): void {
    expect(runCommand($this, 'migrate')->getDisplay())->toContain('Migrada: 2026_01_01_000000_create_authors_table')
        ->and(runCommand($this, 'migrate')->getDisplay())->toContain('Nada para migrar.')
        ->and(runCommand($this, 'migrate:status')->getDisplay())->toContain('2026_01_01_000000_create_authors_table')->toContain('Sim')
        ->and(runCommand($this, 'rollback')->getDisplay())->toContain('Desfeita: 2026_01_01_000000_create_authors_table')
        ->and(runCommand($this, 'migrate:rollback', ['--step' => '2'])->getDisplay())->toContain('Nada para desfazer.');

    runCommand($this, 'migrate');

    expect(runCommand($this, 'migrate:reset')->getDisplay())->toContain('Desfeita: 2026_01_01_000000_create_authors_table');
});

it('gera controllers a partir do stub', function (): void {
    $tester = runCommand($this, 'make:controller', ['name' => 'Admin/Post']);
    $file = $this->basePath . '/app/Http/Controllers/Admin/PostController.php';

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('Criado: app/Http/Controllers/Admin/PostController.php')
        ->and(file_get_contents($file))
        ->toContain('namespace App\Http\Controllers\Admin;')
        ->toContain('final class PostController')
        ->toContain("#[Group(prefix: '/post', name: 'post.')]");
});

it('não sobrescreve arquivos sem --force', function (): void {
    runCommand($this, 'make:controller', ['name' => 'UserController']);

    $again = runCommand($this, 'make:controller', ['name' => 'User']);
    $forced = runCommand($this, 'make:controller', ['name' => 'User', '--force' => true, '--invokable' => true]);

    expect($again->getStatusCode())->toBe(1)
        ->and($again->getDisplay())->toContain('já existe')
        ->and($forced->getStatusCode())->toBe(0)
        ->and(file_get_contents($this->basePath . '/app/Http/Controllers/UserController.php'))->toContain('public function __invoke()');
});

it('usa stubs personalizados da aplicação', function (): void {
    mkdir($this->basePath . '/stubs');
    file_put_contents($this->basePath . '/stubs/middleware.stub', "<?php // {{ namespace }}\\{{ class }}\n");

    runCommand($this, 'make:middleware', ['name' => 'Auth']);

    expect(file_get_contents($this->basePath . '/app/Http/Middleware/Auth.php'))->toBe("<?php // App\\Http\\Middleware\\Auth\n");
});

it('gera migrations e entidades', function (): void {
    runCommand($this, 'make:migration', ['name' => 'create_posts_table']);
    runCommand($this, 'make:migration', ['name' => 'add_slug_to_posts_table']);
    runCommand($this, 'make:entity', ['name' => 'BlogPost']);

    $files = glob($this->basePath . '/database/migrations/*_posts_table.php') ?: [];
    sort($files);

    expect($files)->toHaveCount(2)
        ->and(file_get_contents($files[0]) . file_get_contents($files[1]))->toContain("\$schema->table('posts'")->toContain("\$schema->create('posts'")
        ->and(file_get_contents($this->basePath . '/app/Entity/BlogPost.php'))->toContain("#[Table('blog_posts')]")->toContain('namespace App\Entity;');
});

it('lista as rotas', function (): void {
    $display = runCommand($this, 'route:list')->getDisplay();

    expect($display)
        ->toContain('/closure')
        ->toContain('home')
        ->toContain('Forja\Tests\Fixtures\App\Controllers\HomeController@index')
        ->toContain('3 rota(s).');
});

it('gera e remove os caches de rotas e configuração', function (): void {
    withoutClosureRoutes($this);

    runCommand($this, 'route:cache');
    runCommand($this, 'config:cache');

    expect($this->basePath . '/var/cache/routes.php')->toBeFile()
        ->and($this->basePath . '/var/cache/config.php')->toBeFile();

    runCommand($this, 'route:cache', ['--clear' => true]);
    runCommand($this, 'config:cache', ['--clear' => true]);

    expect(is_file($this->basePath . '/var/cache/routes.php'))->toBeFalse()
        ->and(is_file($this->basePath . '/var/cache/config.php'))->toBeFalse();
});

it('otimiza para produção e limpa os caches', function (): void {
    withoutClosureRoutes($this);

    $display = runCommand($this, 'optimize')->getDisplay();

    expect($display)->toContain('Configuração em cache.')->toContain('Rotas em cache.')->toContain('Container compilado.')->toContain('preload')
        ->and(file_get_contents($this->basePath . '/var/cache/container.php'))->toContain('HomeController');

    $optimized = Application::create($this->basePath);

    expect((string) $optimized->handle(new Nyholm\Psr7\ServerRequest('GET', '/'))->getBody())->toBe('Bem-vindo à Forja Fixture');

    expect(runCommand($this, 'cache:clear')->getDisplay())->toContain('4 arquivo(s)')
        ->and(glob($this->basePath . '/var/cache/*'))->toBe([]);
});

it('roda pelo binário forja', function (): void {
    $process = proc_open([PHP_BINARY, dirname(__DIR__, 3) . '/bin/forja', 'list', '--raw'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->basePath);
    assert(is_resource($process));
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    $status = proc_close($process);

    expect($status)->toBe(0, (string) $errors)
        ->and($output)->toContain('migrate')->toContain('route:list');
});
