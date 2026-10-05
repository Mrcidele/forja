<?php

declare(strict_types=1);

namespace Forja\Container;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;
use UnitEnum;

/**
 * Gera um arquivo PHP com fábricas para as classes informadas, eliminando a
 * reflection do autowiring em produção.
 *
 * Classes que dependem de valores impossíveis de exportar (objetos como valor
 * padrão, parâmetros escalares obrigatórios) são ignoradas e continuam sendo
 * resolvidas por reflection.
 */
final class ContainerCompiler
{
    /**
     * @param iterable<string> $classes
     */
    public function compile(iterable $classes): string
    {
        $entries = [];

        foreach ($classes as $class) {
            $factory = $this->compileClass($class);

            if ($factory !== null) {
                $entries[$class] = sprintf("    %s => static fn (\\%s \$c): \\%s => %s,\n", var_export($class, true), Container::class, $class, $factory);
            }
        }

        ksort($entries);

        return "<?php\n\ndeclare(strict_types=1);\n\n// Gerado por " . self::class . ". Não edite.\n\nreturn [\n" . implode('', $entries) . "];\n";
    }

    /**
     * Compila e grava o arquivo de forma atômica.
     *
     * @param iterable<string> $classes
     */
    public function dump(iterable $classes, string $file): void
    {
        $directory = dirname($file);

        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Não foi possível criar o diretório [%s].', $directory));
        }

        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (file_put_contents($temporary, $this->compile($classes)) === false || ! rename($temporary, $file)) {
            @unlink($temporary);

            throw new RuntimeException(sprintf('Não foi possível gravar o container compilado em [%s].', $file));
        }
    }

    /**
     * Carrega as fábricas de um arquivo gerado por dump().
     *
     * @return array<string, \Closure(Container): object>
     */
    public static function load(string $file): array
    {
        $factories = require $file;

        if (! is_array($factories)) {
            throw new RuntimeException(sprintf('O arquivo [%s] não contém um container compilado.', $file));
        }

        /** @var array<string, \Closure(Container): object> $factories */
        return $factories;
    }

    private function compileClass(string $class): ?string
    {
        if (! class_exists($class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->isInstantiable()) {
            return null;
        }

        $constructor = $reflection->getConstructor();
        $arguments = $constructor instanceof ReflectionMethod ? $this->compileArguments($constructor) : [];

        if ($arguments === null) {
            return null;
        }

        return sprintf('new \\%s(%s)', $reflection->getName(), implode(', ', $arguments));
    }

    /**
     * @return list<string>|null
     */
    private function compileArguments(ReflectionMethod $constructor): ?array
    {
        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                break;
            }

            $argument = $this->compileArgument($parameter);

            if ($argument === null) {
                return null;
            }

            $arguments[] = $argument;
        }

        return $arguments;
    }

    private function compileArgument(ReflectionParameter $parameter): ?string
    {
        $class = Container::parameterClass($parameter);
        $hasFallback = $parameter->isDefaultValueAvailable() || $parameter->allowsNull();
        $fallback = $hasFallback ? $this->fallback($parameter) : null;

        if ($hasFallback && $fallback === null) {
            return null;
        }

        if ($class === null) {
            return $fallback;
        }

        $id = var_export($class, true);

        if ($fallback === null) {
            return sprintf('$c->get(%s)', $id);
        }

        return sprintf('($c->has(%1$s) ? $c->get(%1$s) : %2$s)', $id, $fallback);
    }

    /**
     * Código do valor usado quando o container não resolve o parâmetro, ou null
     * se o valor não puder ser exportado.
     */
    private function fallback(ReflectionParameter $parameter): ?string
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $this->export($parameter->getDefaultValue());
        }

        return 'null';
    }

    private function export(mixed $value): ?string
    {
        if ($value instanceof UnitEnum) {
            return '\\' . $value::class . '::' . $value->name;
        }

        if (is_array($value)) {
            $items = [];

            foreach ($value as $key => $item) {
                $exported = $this->export($item);

                if ($exported === null) {
                    return null;
                }

                $items[] = var_export($key, true) . ' => ' . $exported;
            }

            return '[' . implode(', ', $items) . ']';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return var_export($value, true);
        }

        return null;
    }
}
