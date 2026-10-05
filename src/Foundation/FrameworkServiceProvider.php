<?php

declare(strict_types=1);

namespace Forja\Foundation;

use Closure;
use Forja\Cache\FileCache;
use Forja\Config\AppConfig;
use Forja\Config\Config;
use Forja\Container\Container;
use Forja\Container\ServiceProvider;
use Forja\Database\Connection;
use Forja\Database\DatabaseConfig;
use Forja\Database\Migrations\MigrationRepository;
use Forja\Database\Migrations\Migrator;
use Forja\Database\ORM\EntityManager;
use Forja\Database\Schema\Schema;
use Forja\Error\ErrorHandler;
use Forja\Error\ExceptionHandler;
use Forja\Error\ExceptionHandlerInterface;
use Forja\Http\Emitter\EmitterInterface;
use Forja\Http\Emitter\SapiEmitter;
use Forja\Http\Kernel;
use Forja\Http\Middleware\CorsOptions;
use Forja\Http\Middleware\CsrfMiddleware;
use Forja\Http\Middleware\RateLimitMiddleware;
use Forja\Http\RequestFactory;
use Forja\Http\ResponseFactory;
use Forja\RateLimit\RateLimiter;
use Forja\Routing\AttributeRouteLoader;
use Forja\Routing\ControllerInvoker;
use Forja\Routing\RouteCache;
use Forja\Routing\RouteCollection;
use Forja\Routing\RouteHandler;
use Forja\Routing\Router;
use Forja\Session\CacheSessionStore;
use Forja\Session\SessionOptions;
use Forja\Session\SessionStoreInterface;
use InvalidArgumentException;
use Psr\Http\Server\MiddlewareInterface;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

/**
 * Registra os serviços centrais do framework a partir da configuração.
 */
final class FrameworkServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function register(Container $container): void
    {
        $this->registerHttp($container);
        $this->registerErrors($container);
        $this->registerStorage($container);
        $this->registerMiddleware($container);
        $this->registerRouting($container);
        $this->registerDatabase($container);
    }

    private function registerHttp(Container $container): void
    {
        $container->singleton(ResponseFactory::class);
        $container->singleton(RequestFactory::class);
        $container->singleton(EmitterInterface::class, SapiEmitter::class);

        $container->singleton(Kernel::class, static fn (RouteHandler $handler, Config $config, Container $container): Kernel => new Kernel(
            $handler,
            self::middlewareList($config->array('http.middleware', [])),
            $container,
        ));
    }

    private function registerErrors(Container $container): void
    {
        $container->singleton(ExceptionHandlerInterface::class, static fn (AppConfig $app, Config $config): ExceptionHandler => new ExceptionHandler(
            debug: $app->debug,
            apiPrefixes: array_values(array_filter($config->array('http.api_prefixes', ['/api']), is_string(...))),
        ));

        $container->singleton(ErrorHandler::class);
    }

    private function registerStorage(Container $container): void
    {
        $app = $this->app;

        $container->singleton(CacheInterface::class, static fn (): FileCache => new FileCache($app->storagePath('cache/data')));
        $container->singleton(SessionStoreInterface::class, static fn (): CacheSessionStore => new CacheSessionStore(new FileCache($app->storagePath('sessions'))));
        $container->singleton(RateLimiter::class);
    }

    private function registerMiddleware(Container $container): void
    {
        $container->singleton(SessionOptions::class, static function (Config $config): SessionOptions {
            $sameSite = $config->string('session.same_site', 'Lax');

            $domain = $config->get('session.domain');

            return new SessionOptions(
                cookieName: $config->string('session.cookie', 'forja_session'),
                lifetime: $config->int('session.lifetime', 7200),
                path: $config->string('session.path', '/'),
                domain: is_string($domain) ? $domain : null,
                secure: $config->bool('session.secure', false),
                httpOnly: $config->bool('session.http_only', true),
                sameSite: in_array($sameSite, ['Lax', 'Strict', 'None'], true) ? $sameSite : 'Lax',
            );
        });

        $container->singleton(CorsOptions::class, static fn (Config $config): CorsOptions => new CorsOptions(
            allowedOrigins: self::strings($config->array('cors.allowed_origins', ['*'])),
            allowedMethods: self::strings($config->array('cors.allowed_methods', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])),
            allowedHeaders: self::strings($config->array('cors.allowed_headers', ['*'])),
            exposedHeaders: self::strings($config->array('cors.exposed_headers', [])),
            allowCredentials: $config->bool('cors.allow_credentials', false),
            maxAge: $config->int('cors.max_age', 0),
        ));

        $container->singleton(CsrfMiddleware::class, static fn (Config $config): CsrfMiddleware => new CsrfMiddleware(
            self::strings($config->array('http.csrf_except', [])),
        ));

        $container->singleton(RateLimitMiddleware::class, static fn (RateLimiter $limiter, Config $config): RateLimitMiddleware => new RateLimitMiddleware(
            $limiter,
            $config->int('http.rate_limit.max_attempts', 60),
            $config->int('http.rate_limit.decay_seconds', 60),
        ));
    }

    private function registerRouting(Container $container): void
    {
        $app = $this->app;

        $container->singleton(Router::class, static function (Config $config, Container $container) use ($app): Router {
            $cache = $app->cachePath('routes.php');

            if (is_file($cache)) {
                return Router::fromCompiled(RouteCache::load($cache));
            }

            $router = new Router();
            $loader = new AttributeRouteLoader();

            foreach (self::strings($config->array('routing.controllers', [])) as $directory) {
                $loader->loadDirectory($router->routes(), $directory);
            }

            foreach (self::strings($config->array('routing.files', [])) as $file) {
                $definition = require $file;

                if (! $definition instanceof Closure) {
                    throw new RuntimeException(sprintf('O arquivo de rotas [%s] deve retornar uma closure que recebe a RouteCollection.', $file));
                }

                $container->call($definition, [RouteCollection::class => $router->routes()]);
            }

            return $router;
        });

        $container->singleton(ControllerInvoker::class);
        $container->singleton(RouteHandler::class);
    }

    private function registerDatabase(Container $container): void
    {
        $app = $this->app;

        $container->singleton(Connection::class, static function (Config $config): Connection {
            $name = $config->string('database.default', 'sqlite');
            $connection = $config->get('database.connections.' . $name);

            if (! is_array($connection)) {
                throw new InvalidArgumentException(sprintf('Conexão de banco [%s] não configurada em database.connections.', $name));
            }

            /** @var array<string, mixed> $connection */
            return new Connection(DatabaseConfig::fromArray($connection));
        });

        $container->singleton(Schema::class);
        $container->singleton(EntityManager::class);
        $container->singleton(MigrationRepository::class);
        $container->singleton(Migrator::class, static fn (Connection $connection, MigrationRepository $repository, Config $config): Migrator => new Migrator(
            $connection,
            $repository,
            $config->string('database.migrations', $app->basePath('database/migrations')),
        ));
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<string>
     */
    private static function strings(array $values): array
    {
        return array_values(array_filter($values, is_string(...)));
    }

    /**
     * Valida a lista em vez de ignorar entradas inválidas: um middleware de
     * segurança digitado errado não pode sumir silenciosamente.
     *
     * @param array<mixed> $values
     *
     * @return list<class-string<MiddlewareInterface>>
     */
    private static function middlewareList(array $values): array
    {
        $middleware = [];

        foreach ($values as $value) {
            if (! is_string($value) || ! is_subclass_of($value, MiddlewareInterface::class)) {
                throw new InvalidArgumentException(sprintf('Middleware inválido em http.middleware: [%s].', is_string($value) ? $value : get_debug_type($value)));
            }

            $middleware[] = $value;
        }

        return $middleware;
    }
}
