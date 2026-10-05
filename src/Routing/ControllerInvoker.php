<?php

declare(strict_types=1);

namespace Forja\Routing;

use BackedEnum;
use Closure;
use Forja\Container\Container;
use Forja\Http\Exception\BadRequestHttpException;
use Forja\Http\Exception\NotFoundHttpException;
use Forja\Http\ResponseFactory;
use Forja\Routing\Attribute\FromBody;
use Forja\Routing\Attribute\FromQuery;
use Forja\Validation\DtoMapper;
use LogicException;
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
        private DtoMapper $mapper = new DtoMapper(),
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
            ...$this->objectAttributes($request),
            ServerRequestInterface::class => $request,
            RequestInterface::class => $request,
            Route::class => $route,
            ...$this->convertParameters($reflection, $parameters),
            ...$this->mapRequestData($reflection, $request),
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
     * Parâmetros marcados com #[FromBody] ou #[FromQuery] recebem um DTO
     * preenchido e validado a partir da requisição.
     *
     * @return array<string, object>
     */
    private function mapRequestData(ReflectionFunctionAbstract $reflection, ServerRequestInterface $request): array
    {
        $mapped = [];

        foreach ($reflection->getParameters() as $parameter) {
            $fromBody = $parameter->getAttributes(FromBody::class) !== [];
            $fromQuery = $parameter->getAttributes(FromQuery::class) !== [];
            $type = $parameter->getType();

            if (! $fromBody && ! $fromQuery) {
                continue;
            }

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin() || ! class_exists($type->getName())) {
                throw new LogicException(sprintf('O parâmetro $%s precisa ser tipado com uma classe para usar #[FromBody]/#[FromQuery].', $parameter->getName()));
            }

            $data = $fromBody ? $this->payload($request) : $request->getQueryParams();
            $mapped[$parameter->getName()] = $this->mapper->map($type->getName(), $data);
        }

        return $mapped;
    }

    /**
     * Corpo da requisição como array: formulário já interpretado ou JSON.
     *
     * @return array<mixed>
     */
    private function payload(ServerRequestInterface $request): array
    {
        $parsed = $request->getParsedBody();

        if (is_array($parsed) && $parsed !== []) {
            return $parsed;
        }

        $raw = (string) $request->getBody();

        if (trim($raw) === '') {
            return [];
        }

        $contentType = strtolower($request->getHeaderLine('Content-Type'));

        if (str_contains($contentType, 'json')) {
            $data = json_decode($raw, true);

            if (! is_array($data)) {
                throw new BadRequestHttpException('O corpo da requisição não é um JSON válido.');
            }

            return $data;
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($raw, $data);

            return $data;
        }

        return [];
    }

    /**
     * Atributos da requisição registrados pelo nome da classe (ex.: a sessão)
     * ficam disponíveis para injeção por tipo.
     *
     * @return array<string, object>
     */
    private function objectAttributes(ServerRequestInterface $request): array
    {
        $objects = [];

        foreach ($request->getAttributes() as $name => $value) {
            if (is_string($name) && is_object($value) && (class_exists($name) || interface_exists($name))) {
                $objects[$name] = $value;
            }
        }

        return $objects;
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
