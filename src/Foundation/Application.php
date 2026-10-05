<?php

declare(strict_types=1);

namespace Forja\Foundation;

use Forja\Config\AppConfig;
use Forja\Config\Config;
use Forja\Config\ConfigLoader;
use Forja\Config\Env;
use Forja\Config\Environment;
use Forja\Container\Container;
use Forja\Container\ContainerCompiler;
use Forja\Container\ServiceProvider;
use Forja\Error\ErrorHandler;
use Forja\Http\Emitter\EmitterInterface;
use Forja\Http\Kernel;
use Forja\Http\RequestFactory;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Inicializa a aplicação: carrega .env e configuração (ou o cache dela),
 * registra os service providers e expõe o ciclo HTTP.
 *
 * Estrutura esperada a partir do diretório base:
 * config/*.php, .env e var/ (caches, sessões e logs).
 */
final class Application
{
    public readonly Container $container;

    private bool $bootstrapped = false;

    private readonly string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->container = new Container();
        $this->container->instance(self::class, $this);
    }

    public static function create(string $basePath): self
    {
        $app = new self($basePath);
        $app->bootstrap();

        return $app;
    }

    public function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }

        $this->bootstrapped = true;
        $config = $this->loadConfiguration();
        $appConfig = AppConfig::fromConfig($config);

        $this->container->instance(Config::class, $config);
        $this->container->instance(AppConfig::class, $appConfig);
        $this->container->instance(Environment::class, $appConfig->environment);
        date_default_timezone_set($appConfig->timezone);

        if (is_file($this->cachePath('container.php'))) {
            $this->container->loadCompiled(ContainerCompiler::load($this->cachePath('container.php')));
        }

        $this->container->register(FrameworkServiceProvider::class);

        foreach ($config->array('app.providers', []) as $provider) {
            if (! is_string($provider) || ! is_subclass_of($provider, ServiceProvider::class)) {
                throw new InvalidArgumentException(sprintf('Service provider inválido em app.providers: [%s].', is_string($provider) ? $provider : get_debug_type($provider)));
            }

            $this->container->register($provider);
        }

        $this->container->boot();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->container->get(Kernel::class)->handle($request);
    }

    /**
     * Atende a requisição atual (superglobais) e envia a resposta.
     */
    public function run(): void
    {
        $this->container->get(ErrorHandler::class)->register();
        $request = $this->container->get(RequestFactory::class)->fromGlobals();

        $this->container->get(EmitterInterface::class)->emit($this->handle($request));
    }

    public function config(): Config
    {
        return $this->container->get(Config::class);
    }

    public function environment(): Environment
    {
        return $this->container->get(Environment::class);
    }

    public function basePath(string $path = ''): string
    {
        return $this->join($this->basePath, $path);
    }

    public function configPath(string $path = ''): string
    {
        return $this->join($this->basePath . '/config', $path);
    }

    public function storagePath(string $path = ''): string
    {
        return $this->join($this->basePath . '/var', $path);
    }

    public function cachePath(string $path = ''): string
    {
        return $this->join($this->basePath . '/var/cache', $path);
    }

    private function loadConfiguration(): Config
    {
        $loader = new ConfigLoader();
        $cached = $this->cachePath('config.php');

        // Com a configuração em cache, o .env não é lido (os valores já estão resolvidos).
        if (is_file($cached)) {
            return new Config($loader->loadCached($cached));
        }

        Env::load($this->basePath);

        return new Config($loader->load($this->configPath()));
    }

    private function join(string $base, string $path): string
    {
        $base = rtrim($base, '/');

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}
