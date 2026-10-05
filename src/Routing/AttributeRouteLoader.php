<?php

declare(strict_types=1);

namespace Forja\Routing;

use Forja\Routing\Attribute\Group;
use Forja\Routing\Attribute\Route as RouteAttribute;
use Forja\Support\ClassFinder;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

/**
 * Lê os atributos #[Route] e #[Group] dos controllers e registra as rotas.
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

        $routes->group($group->prefix, function (RouteCollection $routes) use ($reflection, $class): void {
            foreach ($this->routeAttributes($reflection) as $attribute) {
                $routes->add($attribute->methods, $attribute->path, $class, $attribute->name);
            }

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Apenas métodos declarados na própria classe, para não duplicar rotas herdadas.
                if ($method->isStatic() || $method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                foreach ($this->routeAttributes($method) as $attribute) {
                    $routes->add($attribute->methods, $attribute->path, [$class, $method->getName()], $attribute->name);
                }
            }
        }, $group->name);
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
}
