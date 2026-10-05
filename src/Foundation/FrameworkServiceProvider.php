<?php

declare(strict_types=1);

namespace Forja\Foundation;

use Forja\Cache\FileCache;
use Forja\Config\AppConfig;
use Forja\Config\Config;
use Forja\Container\Container;
use Forja\Container\ServiceProvider;
use Forja\Database\Connection;
use Forja\Database\DatabaseConfig;
use Forja\Database\Event\QueryExecuted;
use Forja\Database\Migrations\MigrationRepository;
use Forja\Database\Migrations\Migrator;
use Forja\Database\ORM\EntityManager;
use Forja\Database\Schema\Schema;
use Forja\Error\ErrorHandler;
use Forja\Error\ExceptionHandler;
use Forja\Error\ExceptionHandlerInterface;
use Forja\Events\EventDispatcher;
use Forja\Events\ListenerProvider;
use Forja\Http\Emitter\EmitterInterface;
use Forja\Http\Emitter\SapiEmitter;
use Forja\Http\Kernel;
use Forja\Http\Middleware\CorsOptions;
use Forja\Http\Middleware\CsrfMiddleware;
use Forja\Http\Middleware\RateLimitMiddleware;
use Forja\Http\RequestFactory;
use Forja\Http\ResponseFactory;
use Forja\Log\Handler\StreamHandler;
use Forja\Log\Level;
use Forja\Log\Logger;
use Forja\RateLimit\RateLimiter;
use Forja\Routing\ControllerInvoker;
use Forja\Routing\RouteCache;
use Forja\Routing\RouteHandler;
use Forja\Routing\RouteLoader;
use Forja\Routing\Router;
use Forja\Session\CacheSessionStore;
use Forja\Session\SessionOptions;
use Forja\Session\SessionStoreInterface;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

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
        $this->registerEventsAndLogging($container);
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

    private function registerEventsAndLogging(Container $container): void
    {
        $app = $this->app;

        $container->singleton(ListenerProvider::class, static function (Config $config, Container $container): ListenerProvider {
            $provider = new ListenerProvider($container);

            foreach ($config->array('events.listeners', []) as $event => $listeners) {
                if (! is_string($event) || ! class_exists($event) && ! interface_exists($event)) {
                    throw new InvalidArgumentException(sprintf('Evento inválido em events.listeners: [%s].', is_string($event) ? $event : get_debug_type($event)));
                }

                foreach (is_array($listeners) ? $listeners : [$listeners] as $listener) {
                    if (is_string($listener) && class_exists($listener)) {
                        $provider->listen($event, $listener);
                    } elseif (is_callable($listener)) {
                        $provider->listen($event, $listener);
                    } else {
                        throw new InvalidArgumentException(sprintf('Ouvinte inválido para o evento [%s].', $event));
                    }
                }
            }

            return $provider;
        });
        $container->bind(ListenerProviderInterface::class, ListenerProvider::class);
        $container->singleton(EventDispatcherInterface::class, static fn (ListenerProvider $provider): EventDispatcher => new EventDispatcher($provider));

        $container->singleton(LoggerInterface::class, static fn (Config $config, AppConfig $appConfig): Logger => new Logger(
            $config->string('logging.channel', strtolower(preg_replace('/\W+/', '-', $appConfig->name) ?? 'app')),
            [new StreamHandler(
                $config->string('logging.path', $app->storagePath('logs/forja.log')),
                Level::fromName($config->string('logging.level', 'debug')),
            )],
        ));
    }

    private function registerErrors(Container $container): void
    {
        $container->singleton(ExceptionHandlerInterface::class, static fn (AppConfig $app, Config $config, LoggerInterface $logger): ExceptionHandler => new ExceptionHandler(
            debug: $app->debug,
            apiPrefixes: array_values(array_filter($config->array('http.api_prefixes', ['/api']), is_string(...))),
            logger: $logger,
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

        $container->singleton(Router::class, static function (RouteLoader $loader) use ($app): Router {
            $cache = $app->cachePath('routes.php');

            return is_file($cache) ? Router::fromCompiled(RouteCache::load($cache)) : $loader->router();
        });

        $container->singleton(RouteLoader::class, static fn (Config $config, Container $container): RouteLoader => new RouteLoader(
            $container,
            self::strings($config->array('routing.controllers', [])),
            self::strings($config->array('routing.files', [])),
        ));
        $container->singleton(ControllerInvoker::class);
        $container->singleton(RouteHandler::class);
    }

    private function registerDatabase(Container $container): void
    {
        $app = $this->app;

        $container->singleton(Connection::class, static function (Config $config, EventDispatcherInterface $events): Connection {
            $name = $config->string('database.default', 'sqlite');
            $settings = $config->get('database.connections.' . $name);

            if (! is_array($settings)) {
                throw new InvalidArgumentException(sprintf('Conexão de banco [%s] não configurada em database.connections.', $name));
            }

            /** @var array<string, mixed> $settings */
            $connection = new Connection(DatabaseConfig::fromArray($settings));
            $connection->listen(static function (string $sql, array $bindings, float $time) use ($events, $connection): void {
                $events->dispatch(new QueryExecuted($sql, $bindings, $time, $connection->driver()));
            });

            return $connection;
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
