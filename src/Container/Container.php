<?php

declare(strict_types=1);

namespace Forja\Container;

use Closure;
use Forja\Container\Exception\CircularDependencyException;
use Forja\Container\Exception\ContainerException;
use Forja\Container\Exception\NotFoundException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Container de injeção de dependências compatível com PSR-11.
 *
 * Classes concretas são resolvidas por autowiring do construtor mesmo sem
 * registro prévio; bindings explícitos definem singletons, fábricas e a
 * implementação usada para cada interface.
 */
final class Container implements ContainerInterface
{
    /** @var array<string, Definition> */
    private array $definitions = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, true> */
    private array $resolving = [];

    /** @var array<string, Closure(self): object> */
    private array $compiled = [];

    /** @var array<class-string, true> */
    private array $autowired = [];

    /** @var array<class-string, bool> */
    private array $instantiable = [];

    /** @var array<class-string<ServiceProvider>, ServiceProvider> */
    private array $providers = [];

    private bool $booted = false;

    public function __construct()
    {
        $this->instance(self::class, $this);
        $this->instance(ContainerInterface::class, $this);
    }

    /**
     * Registra uma entrada. Sem $concrete, o próprio $id é construído por autowiring.
     *
     * @param Closure|string|null $concrete fábrica (com dependências injetadas) ou classe/entrada alvo
     */
    public function bind(string $id, Closure|string|null $concrete = null, bool $shared = false): void
    {
        unset($this->instances[$id]);
        $this->definitions[$id] = new Definition($concrete ?? $id, $shared);
    }

    /**
     * Registra uma entrada compartilhada: construída uma vez e reutilizada.
     */
    public function singleton(string $id, Closure|string|null $concrete = null): void
    {
        $this->bind($id, $concrete, shared: true);
    }

    /**
     * Registra uma fábrica: cada get() devolve uma nova instância.
     */
    public function factory(string $id, Closure $factory): void
    {
        $this->bind($id, $factory, shared: false);
    }

    /**
     * Registra um valor já construído.
     */
    public function instance(string $id, mixed $value): void
    {
        unset($this->definitions[$id]);
        $this->instances[$id] = $value;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances)
            || isset($this->definitions[$id])
            || isset($this->compiled[$id])
            || $this->isInstantiable($id);
    }

    /**
     * @template T of object
     *
     * @param string|class-string<T> $id
     *
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        return $this->withCircularGuard($id, function () use ($id): mixed {
            $definition = $this->definitions[$id] ?? null;

            if ($definition === null) {
                return $this->build($id);
            }

            $value = $this->resolveDefinition($id, $definition, []);

            if ($definition->shared) {
                $this->instances[$id] = $value;
            }

            return $value;
        });
    }

    /**
     * Constrói sempre uma nova instância, ignorando singletons já criados.
     * $parameters substitui argumentos por nome do parâmetro ou por tipo.
     *
     * @template T of object
     *
     * @param string|class-string<T> $id
     * @param array<string, mixed> $parameters
     *
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function make(string $id, array $parameters = []): mixed
    {
        return $this->withCircularGuard($id, function () use ($id, $parameters): mixed {
            $definition = $this->definitions[$id] ?? null;

            return $definition === null
                ? $this->build($id, $parameters)
                : $this->resolveDefinition($id, $definition, $parameters);
        });
    }

    /**
     * Invoca um callable injetando suas dependências.
     *
     * Aceita closures, objetos invocáveis, nomes de função, "Classe::metodo" e
     * [objeto|classe, metodo]. Quando a classe vem como string, a instância é
     * obtida do container (exceto para métodos estáticos).
     *
     * @param callable|array{object|string, string}|string $callable
     * @param array<string, mixed> $parameters argumentos por nome do parâmetro ou por tipo
     */
    public function call(callable|array|string $callable, array $parameters = []): mixed
    {
        [$reflection, $target] = $this->reflectCallable($callable);
        $arguments = $this->resolveParameters($reflection->getParameters(), $parameters, $this->describe($reflection));

        if ($reflection instanceof ReflectionMethod) {
            return $reflection->invokeArgs($target, $arguments);
        }

        return $reflection->invokeArgs($arguments);
    }

    /**
     * Registra um service provider. Se o container já foi inicializado, o
     * provider é inicializado imediatamente.
     *
     * @param ServiceProvider|class-string<ServiceProvider> $provider
     */
    public function register(ServiceProvider|string $provider): ServiceProvider
    {
        $instance = is_string($provider) ? $this->make($provider) : $provider;

        if (isset($this->providers[$instance::class])) {
            return $this->providers[$instance::class];
        }

        $this->providers[$instance::class] = $instance;
        $instance->register($this);

        if ($this->booted) {
            $this->bootProvider($instance);
        }

        return $instance;
    }

    /**
     * Executa boot() de todos os providers registrados, uma única vez.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        foreach ($this->providers as $provider) {
            $this->bootProvider($provider);
        }
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    public function providers(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Chama reset() nos serviços já instanciados que implementam
     * ResettableInterface (usado entre requisições em worker mode).
     */
    public function resetServices(): void
    {
        foreach ($this->instances as $instance) {
            if ($instance instanceof ResettableInterface) {
                $instance->reset();
            }
        }
    }

    /**
     * Carrega fábricas geradas pelo ContainerCompiler, que substituem a
     * reflection na construção das classes compiladas.
     *
     * @param array<string, Closure(self): object> $factories
     */
    public function loadCompiled(array $factories): void
    {
        $this->compiled = $factories + $this->compiled;
    }

    /**
     * Classes construídas por reflection até agora; útil para decidir o que compilar.
     *
     * @return list<class-string>
     */
    public function autowiredClasses(): array
    {
        return array_keys($this->autowired);
    }

    /**
     * Ids registrados por bind/singleton/factory e o alvo de cada um quando é uma classe.
     *
     * @return array<string, string|null>
     */
    public function bindings(): array
    {
        return array_map(
            static fn (Definition $definition): ?string => is_string($definition->concrete) ? $definition->concrete : null,
            $this->definitions,
        );
    }

    /**
     * @param Closure(): mixed $resolver
     */
    private function withCircularGuard(string $id, Closure $resolver): mixed
    {
        if (isset($this->resolving[$id])) {
            throw CircularDependencyException::forChain([...array_keys($this->resolving), $id]);
        }

        $this->resolving[$id] = true;

        try {
            return $resolver();
        } finally {
            unset($this->resolving[$id]);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function resolveDefinition(string $id, Definition $definition, array $parameters): mixed
    {
        $concrete = $definition->concrete;

        if ($concrete instanceof Closure) {
            return $this->call($concrete, $parameters);
        }

        if ($concrete === $id) {
            return $this->build($id, $parameters);
        }

        return $parameters === [] ? $this->get($concrete) : $this->make($concrete, $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function build(string $class, array $parameters = []): object
    {
        if ($parameters === [] && isset($this->compiled[$class])) {
            return ($this->compiled[$class])($this);
        }

        if (! class_exists($class)) {
            throw NotFoundException::forId($class);
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->isInstantiable()) {
            throw NotFoundException::notInstantiable($class);
        }

        $this->autowired[$class] = true;
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = $this->resolveParameters($constructor->getParameters(), $parameters, $class . '::__construct()');

        return $reflection->newInstanceArgs($arguments);
    }

    /**
     * @param array<ReflectionParameter> $reflectionParameters
     * @param array<string, mixed> $overrides
     *
     * @return list<mixed>
     */
    private function resolveParameters(array $reflectionParameters, array $overrides, string $context): array
    {
        $arguments = [];

        foreach ($reflectionParameters as $parameter) {
            $name = $parameter->getName();
            $class = self::parameterClass($parameter);

            if (array_key_exists($name, $overrides)) {
                $value = $overrides[$name];
            } elseif ($class !== null && array_key_exists($class, $overrides)) {
                $value = $overrides[$class];
            } elseif ($class !== null && $this->has($class)) {
                $value = $this->get($class);
            } elseif ($parameter->isVariadic()) {
                break;
            } elseif ($parameter->isDefaultValueAvailable()) {
                $value = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $value = null;
            } else {
                throw ContainerException::unresolvableParameter($name, $context);
            }

            if ($parameter->isVariadic()) {
                array_push($arguments, ...(is_array($value) ? array_values($value) : [$value]));

                break;
            }

            $arguments[] = $value;
        }

        return $arguments;
    }

    /**
     * Nome da classe declarada no tipo do parâmetro, se for um tipo de classe simples.
     *
     * @internal usado também pelo ContainerCompiler
     */
    public static function parameterClass(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = $type->getName();

        if ($name === 'self' || $name === 'static') {
            return $parameter->getDeclaringClass()?->getName();
        }

        return $name;
    }

    private function isInstantiable(string $id): bool
    {
        if (! class_exists($id)) {
            return false;
        }

        return $this->instantiable[$id] ??= new ReflectionClass($id)->isInstantiable();
    }

    /**
     * @param callable|array<mixed>|string $callable
     *
     * @return array{ReflectionFunction|ReflectionMethod, object|null}
     */
    private function reflectCallable(callable|array|string $callable): array
    {
        try {
            if ($callable instanceof Closure) {
                return [new ReflectionFunction($callable), null];
            }

            if (is_string($callable) && str_contains($callable, '::')) {
                $callable = explode('::', $callable, 2);
            }

            if (is_string($callable)) {
                return [new ReflectionFunction($callable), null];
            }

            if (is_array($callable)) {
                $target = $callable[0] ?? null;
                $method = $callable[1] ?? null;

                if (! is_string($method) || (! is_object($target) && ! is_string($target))) {
                    throw ContainerException::invalidCallable();
                }

                $reflection = new ReflectionMethod($target, $method);

                if ($reflection->isStatic()) {
                    return [$reflection, null];
                }

                $instance = is_string($target) ? $this->get($target) : $target;

                if (! is_object($instance)) {
                    throw ContainerException::invalidCallable();
                }

                return [$reflection, $instance];
            }

            if (is_object($callable) && method_exists($callable, '__invoke')) {
                return [new ReflectionMethod($callable, '__invoke'), $callable];
            }
        } catch (ReflectionException $exception) {
            throw new ContainerException($exception->getMessage(), 0, $exception);
        }

        throw ContainerException::invalidCallable();
    }

    private function describe(ReflectionFunctionAbstract $reflection): string
    {
        if ($reflection instanceof ReflectionMethod) {
            return $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName() . '()';
        }

        return $reflection->isClosure() ? 'closure' : $reflection->getName() . '()';
    }

    private function bootProvider(ServiceProvider $provider): void
    {
        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }
    }
}
