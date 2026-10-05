<?php

declare(strict_types=1);

namespace Forja\Routing;

use BackedEnum;
use Closure;
use Forja\Container\Container;
use Forja\Http\Exception\NotFoundHttpException;
use Forja\Http\ResponseFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionEnum;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Executa o handler de uma rota: resolve o controller pelo container, injeta
 * dependências no método, converte os parâmetros da rota para o tipo declarado
 * e transforma o retorno em resposta.
 */
final readonly class ControllerInvoker
{
    public function __construct(
        private Container $container,
        private ResponseFactory $responses = new ResponseFactory(),
    ) {
    }

    /**
     * @param array<string, string|int> $parameters parâmetros extraídos do caminho
     */
    public function invoke(Route $route, ServerRequestInterface $request, array $parameters): ResponseInterface
    {
        $callable = $this->callable($route);
        $reflection = $callable instanceof Closure
            ? new ReflectionFunction($callable)
            : new ReflectionMethod($callable[0], $callable[1]);

        $arguments = [
            ServerRequestInterface::class => $request,
            RequestInterface::class => $request,
            Route::class => $route,
            ...$this->convertParameters($reflection, $parameters),
        ];

        return $this->responses->fromValue($this->container->call($callable, $arguments));
    }

    /**
     * @return Closure|array{object, string}
     */
    private function callable(Route $route): Closure|array
    {
        $handler = $route->handler;

        if ($handler instanceof Closure) {
            return $handler;
        }

        [$class, $method] = is_string($handler) ? [$handler, '__invoke'] : $handler;
        $controller = $this->container->get($class);

        return [$controller, $method];
    }

    /**
     * @param array<string, string|int> $parameters
     *
     * @return array<string, mixed>
     */
    private function convertParameters(ReflectionFunctionAbstract $reflection, array $parameters): array
    {
        $converted = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $parameters)) {
                $converted[$name] = $this->convert($parameters[$name], $parameter);
            }
        }

        return $converted;
    }

    /**
     * Converte o valor textual do caminho para o tipo do parâmetro. Valores
     * incompatíveis (ex.: "abc" para int) resultam em 404.
     */
    private function convert(string|int $value, ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        $converted = match (true) {
            $typeName === 'int' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            $typeName === 'float' => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
            $typeName === 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            $typeName === 'string', $typeName === 'mixed' => (string) $value,
            is_subclass_of($typeName, BackedEnum::class) => $this->toEnum($typeName, $value),
            default => $value,
        };

        if ($converted === null) {
            throw new NotFoundHttpException(sprintf('Valor inválido para o parâmetro [%s].', $parameter->getName()));
        }

        return $converted;
    }

    /**
     * @param class-string<BackedEnum> $enum
     */
    private function toEnum(string $enum, string|int $value): ?BackedEnum
    {
        $backing = new ReflectionEnum($enum)->getBackingType();

        if ($backing instanceof ReflectionNamedType && $backing->getName() === 'int') {
            $int = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

            return $int === null ? null : $enum::tryFrom($int);
        }

        return $enum::tryFrom((string) $value);
    }
}
