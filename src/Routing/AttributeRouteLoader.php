<?php

declare(strict_types=1);

namespace Forja\Routing;

use Forja\Routing\Attribute\Group;
use Forja\Routing\Attribute\Middleware;
use Forja\Routing\Attribute\Route as RouteAttribute;
use Forja\Support\ClassFinder;
use Psr\Http\Server\MiddlewareInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

/**
 * Lê os atributos #[Route], #[Group] e #[Middleware] dos controllers e registra as rotas.
 */
final readonly class AttributeRouteLoader
{
    public function __construct(
        private ClassFinder $finder = new ClassFinder(),
    ) {
    }

    /**
     * Registra as rotas de todas as classes encontradas no diretório.
     */
    public function loadDirectory(RouteCollection $routes, string $directory): void
    {
        foreach ($this->finder->find($directory) as $class) {
            $this->loadClass($routes, $class);
        }
    }

    /**
     * @param class-string $class
     */
    public function loadClass(RouteCollection $routes, string $class): void
    {
        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return;
        }

        $group = ($reflection->getAttributes(Group::class)[0] ?? null)?->newInstance() ?? new Group();
        $classMiddleware = [...$group->middleware, ...$this->middleware($reflection)];

        $routes->group($group->prefix, function (RouteCollection $routes) use ($reflection, $class): void {
            foreach ($this->routeAttributes($reflection) as $attribute) {
                $routes->add($attribute->methods, $attribute->path, $class, $attribute->name, $attribute->middleware);
            }

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Apenas métodos declarados na própria classe, para não duplicar rotas herdadas.
                if ($method->isStatic() || $method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                $methodMiddleware = $this->middleware($method);

                foreach ($this->routeAttributes($method) as $attribute) {
                    $routes->add(
                        $attribute->methods,
                        $attribute->path,
                        [$class, $method->getName()],
                        $attribute->name,
                        [...$methodMiddleware, ...$attribute->middleware],
                    );
                }
            }
        }, $group->name, $classMiddleware);
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod $reflection
     *
     * @return list<RouteAttribute>
     */
    private function routeAttributes(ReflectionClass|ReflectionMethod $reflection): array
    {
        return array_map(
            static fn (ReflectionAttribute $attribute): RouteAttribute => $attribute->newInstance(),
            $reflection->getAttributes(RouteAttribute::class),
        );
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod $reflection
     *
     * @return list<class-string<MiddlewareInterface>>
     */
    private function middleware(ReflectionClass|ReflectionMethod $reflection): array
    {
        $middleware = [];

        foreach ($reflection->getAttributes(Middleware::class) as $attribute) {
            $middleware = [...$middleware, ...$attribute->newInstance()->middleware];
        }

        return $middleware;
    }
}
