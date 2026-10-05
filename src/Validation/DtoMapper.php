<?php

declare(strict_types=1);

namespace Forja\Validation;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Forja\Validation\Rule\Required;
use ReflectionClass;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;

/**
 * Cria DTOs a partir de arrays (corpo ou query da requisição): converte cada
 * valor para o tipo declarado, aplica as regras de validação e só então
 * instancia o objeto. Erros de tipo e de regra viram ValidationException.
 *
 * Aceita DTOs com construtor (inclusive readonly) ou com propriedades
 * públicas, enums, datas e DTOs aninhados.
 */
final readonly class DtoMapper
{
    public function __construct(
        private Validator $validator = new Validator(),
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @param array<mixed> $data
     *
     * @return T
     *
     * @throws ValidationException
     */
    public function map(string $class, array $data): object
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        $errors = [];
        $arguments = [];
        $assigned = [];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $result = $this->field($parameter, $data, $errors);

            if ($result !== null) {
                $arguments[$parameter->getName()] = $result[0];
            }

            $assigned[$parameter->getName()] = true;
        }

        $properties = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (isset($assigned[$property->getName()]) || $property->isStatic() || $property->isReadOnly()) {
                continue;
            }

            if (! array_key_exists($property->getName(), $data) && Validator::rules($property) === []) {
                continue;
            }

            $result = $this->field($property, $data, $errors);

            if ($result !== null && array_key_exists($property->getName(), $data)) {
                $properties[$property->getName()] = $result[0];
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $object = $reflection->newInstanceArgs($arguments);

        foreach ($properties as $name => $value) {
            $object->{$name} = $value;
        }

        return $object;
    }

    /**
     * Valor convertido e validado do campo, ou null quando houve erro.
     *
     * @param array<mixed> $data
     * @param array<string, list<string>> $errors
     *
     * @return array{mixed}|null
     */
    private function field(ReflectionParameter|ReflectionProperty $reflection, array $data, array &$errors): ?array
    {
        $name = $reflection->getName();
        $rules = Validator::rules($reflection);
        $present = array_key_exists($name, $data) && $data[$name] !== null;

        if (! $present) {
            $default = $this->default($reflection);

            if ($default === null && ! $this->allowsNull($reflection)) {
                $errors[$name] = $this->validator->validateValue($name, null, $rules) ?: [new Required()->validate($name, null) ?? ''];

                return null;
            }

            $value = $default[0] ?? null;
        } else {
            $converted = $this->convert($data[$name], $reflection->getType(), $name);

            if ($converted['errors'] !== []) {
                $errors = [...$errors, ...$converted['errors']];

                return null;
            }

            $value = $converted['value'];
        }

        $messages = $this->validator->validateValue($name, $present ? $value : null, $rules);

        if ($messages !== []) {
            $errors[$name] = $messages;

            return null;
        }

        return [$value];
    }

    /**
     * @return array{value: mixed, errors: array<string, list<string>>}
     */
    private function convert(mixed $value, ?ReflectionType $type, string $field): array
    {
        if (! $type instanceof ReflectionNamedType || $type->getName() === 'mixed') {
            return ['value' => $value, 'errors' => []];
        }

        $name = $type->getName();
        $invalid = static fn (string $message): array => ['value' => null, 'errors' => [$field => [sprintf($message, $field)]]];

        switch (true) {
            case $name === 'string':
                return is_scalar($value) && ! is_bool($value) ? ['value' => (string) $value, 'errors' => []] : $invalid('O campo %s deve ser um texto.');
            case $name === 'int':
                $int = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

                return is_int($int) && ! is_bool($value) ? ['value' => $int, 'errors' => []] : $invalid('O campo %s deve ser um número inteiro.');
            case $name === 'float':
                return is_numeric($value) ? ['value' => (float) $value, 'errors' => []] : $invalid('O campo %s deve ser um número.');
            case $name === 'bool':
                $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                return is_bool($bool) ? ['value' => $bool, 'errors' => []] : $invalid('O campo %s deve ser verdadeiro ou falso.');
            case $name === 'array':
                return is_array($value) ? ['value' => $value, 'errors' => []] : $invalid('O campo %s deve ser uma lista.');
            case is_subclass_of($name, BackedEnum::class):
                return $this->enum($name, $value, $field);
            case $name === DateTimeImmutable::class || $name === DateTimeInterface::class:
                return $this->date($value, $field);
            case class_exists($name) && is_array($value):
                return $this->nested($name, $value, $field);
            case $value instanceof $name:
                return ['value' => $value, 'errors' => []];
            default:
                return $invalid('O campo %s tem um tipo inválido.');
        }
    }

    /**
     * @param class-string<BackedEnum> $enum
     *
     * @return array{value: mixed, errors: array<string, list<string>>}
     */
    private function enum(string $enum, mixed $value, string $field): array
    {
        $backing = new ReflectionEnum($enum)->getBackingType();
        $isInt = $backing instanceof ReflectionNamedType && $backing->getName() === 'int';
        $case = null;

        if ($isInt && filter_var($value, FILTER_VALIDATE_INT) !== false && is_scalar($value)) {
            $case = $enum::tryFrom((int) $value);
        } elseif (! $isInt && is_scalar($value)) {
            $case = $enum::tryFrom((string) $value);
        }

        if ($case !== null) {
            return ['value' => $case, 'errors' => []];
        }

        $allowed = implode(', ', array_map(static fn (BackedEnum $case): string => (string) $case->value, $enum::cases()));

        return ['value' => null, 'errors' => [$field => [sprintf('O campo %s deve ser um destes valores: %s.', $field, $allowed)]]];
    }

    /**
     * @return array{value: mixed, errors: array<string, list<string>>}
     */
    private function date(mixed $value, string $field): array
    {
        if (is_string($value) && $value !== '') {
            try {
                return ['value' => new DateTimeImmutable($value), 'errors' => []];
            } catch (Exception) {
            }
        }

        return ['value' => null, 'errors' => [$field => [sprintf('O campo %s deve ser uma data válida.', $field)]]];
    }

    /**
     * @param class-string $class
     * @param array<mixed> $value
     *
     * @return array{value: mixed, errors: array<string, list<string>>}
     */
    private function nested(string $class, array $value, string $field): array
    {
        try {
            return ['value' => $this->map($class, $value), 'errors' => []];
        } catch (ValidationException $exception) {
            $errors = [];

            foreach ($exception->getErrors() as $key => $messages) {
                $errors[$field . '.' . $key] = $messages;
            }

            return ['value' => null, 'errors' => $errors];
        }
    }

    /**
     * @return array{mixed}|null
     */
    private function default(ReflectionParameter|ReflectionProperty $reflection): ?array
    {
        if ($reflection instanceof ReflectionParameter) {
            return $reflection->isDefaultValueAvailable() ? [$reflection->getDefaultValue()] : null;
        }

        return $reflection->hasDefaultValue() && $reflection->getDefaultValue() !== null ? [$reflection->getDefaultValue()] : null;
    }

    private function allowsNull(ReflectionParameter|ReflectionProperty $reflection): bool
    {
        $type = $reflection->getType();

        return $type === null || $type->allowsNull();
    }
}
