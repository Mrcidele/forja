<?php

declare(strict_types=1);

namespace Forja\Validation;

use Forja\Validation\Rule\RuleInterface;
use ReflectionAttribute;
use ReflectionObject;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Valida objetos e valores a partir dos atributos de regra (#[Required],
 * #[Email], #[Min(3)]...) declarados nas propriedades ou parâmetros.
 */
final class Validator
{
    /**
     * @return array<string, list<string>> mensagens por campo; vazio quando válido
     */
    public function validate(object $object): array
    {
        $errors = [];

        foreach (new ReflectionObject($object)->getProperties() as $property) {
            $rules = self::rules($property);

            if ($rules === []) {
                continue;
            }

            $value = $property->isInitialized($object) ? $property->getValue($object) : null;
            $messages = $this->validateValue($property->getName(), $value, $rules);

            if ($messages !== []) {
                $errors[$property->getName()] = $messages;
            }
        }

        return $errors;
    }

    /**
     * @throws ValidationException
     */
    public function assertValid(object $object): void
    {
        $errors = $this->validate($object);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * @param list<RuleInterface> $rules
     *
     * @return list<string>
     */
    public function validateValue(string $field, mixed $value, array $rules): array
    {
        $messages = [];

        foreach ($rules as $rule) {
            $message = $rule->validate($field, $value);

            if ($message !== null) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * @return list<RuleInterface>
     */
    public static function rules(ReflectionProperty|ReflectionParameter $reflection): array
    {
        return array_map(
            static fn (ReflectionAttribute $attribute): RuleInterface => $attribute->newInstance(),
            $reflection->getAttributes(RuleInterface::class, ReflectionAttribute::IS_INSTANCEOF),
        );
    }
}
