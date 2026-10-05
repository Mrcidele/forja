<?php

declare(strict_types=1);

namespace Forja\Routing;

use Forja\Routing\Exception\InvalidRouteException;

/**
 * Interpreta os placeholders de um caminho: {nome}, {nome:tipo} ou {nome:regex}.
 *
 * @internal
 */
final class RoutePattern
{
    /** Aceita um nível de chaves dentro da regex, como em {code:[a-z]{2}}. */
    public const string PLACEHOLDER = '~\{(\w+)(?::((?:[^{}]|\{[^{}]*\})+))?\}~';

    public const string DEFAULT_REGEX = '[^/]+';

    /** @var array<string, string> */
    public const array TYPES = [
        'int' => '\d+',
        'alpha' => '[A-Za-z]+',
        'alnum' => '[A-Za-z0-9]+',
        'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
        'any' => '.+',
    ];

    /**
     * Quebra o caminho em partes literais e placeholders.
     *
     * @return list<string|array{name: string, regex: string, type: string|null}>
     */
    public static function parse(string $path): array
    {
        preg_match_all(self::PLACEHOLDER, $path, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $parts = [];
        $offset = 0;
        $names = [];

        foreach ($matches as $match) {
            [$whole, $position] = $match[0];
            $name = $match[1][0];
            $constraint = isset($match[2]) && $match[2][1] !== -1 ? $match[2][0] : null;

            if (isset($names[$name])) {
                throw new InvalidRouteException(sprintf('O parâmetro {%s} aparece mais de uma vez em [%s].', $name, $path));
            }

            $names[$name] = true;

            if ($position > $offset) {
                $parts[] = substr($path, $offset, $position - $offset);
            }

            $parts[] = self::placeholder($name, $constraint, $path);
            $offset = $position + strlen($whole);
        }

        if ($offset < strlen($path)) {
            $parts[] = substr($path, $offset);
        }

        foreach ($parts as $part) {
            if (is_string($part) && (str_contains($part, '{') || str_contains($part, '}'))) {
                throw new InvalidRouteException(sprintf('Placeholder malformado em [%s].', $path));
            }
        }

        return $parts;
    }

    public static function isStatic(string $path): bool
    {
        return ! str_contains($path, '{');
    }

    /**
     * @return array{name: string, regex: string, type: string|null}
     */
    private static function placeholder(string $name, ?string $constraint, string $path): array
    {
        if ($constraint === null) {
            return ['name' => $name, 'regex' => self::DEFAULT_REGEX, 'type' => null];
        }

        if (isset(self::TYPES[$constraint])) {
            return ['name' => $name, 'regex' => self::TYPES[$constraint], 'type' => $constraint];
        }

        // Grupos de captura deslocariam a numeração usada no casamento combinado.
        if (preg_match('/(?<!\\\\)\((?!\?)/', $constraint) === 1) {
            throw new InvalidRouteException(sprintf('Use grupos não capturantes (?:...) na regex do parâmetro {%s} em [%s].', $name, $path));
        }

        set_error_handler(static fn (): bool => true);

        try {
            $valid = preg_match('~^' . $constraint . '$~', '') !== false;
        } finally {
            restore_error_handler();
        }

        if (! $valid) {
            throw new InvalidRouteException(sprintf('Regex inválida no parâmetro {%s} em [%s].', $name, $path));
        }

        return ['name' => $name, 'regex' => $constraint, 'type' => null];
    }
}
