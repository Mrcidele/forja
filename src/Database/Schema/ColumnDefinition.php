<?php

declare(strict_types=1);

namespace Forja\Database\Schema;

use InvalidArgumentException;

/**
 * Definição de uma coluna, configurada de forma fluente.
 */
final class ColumnDefinition
{
    public bool $nullable = false;

    public bool $hasDefault = false;

    public mixed $default = null;

    public bool $unique = false;

    public bool $unsigned = false;

    public bool $primary = false;

    /** @var array{table: string, column: string, onDelete: string|null, onUpdate: string|null}|null */
    public ?array $references = null;

    /**
     * @param array<string, int> $parameters tamanho, precisão e escala
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array $parameters = [],
    ) {
    }

    public function nullable(bool $nullable = true): self
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->hasDefault = true;
        $this->default = $value;

        return $this;
    }

    public function unique(): self
    {
        $this->unique = true;

        return $this;
    }

    public function unsigned(): self
    {
        $this->unsigned = true;

        return $this;
    }

    public function primary(): self
    {
        $this->primary = true;

        return $this;
    }

    /**
     * Cria a chave estrangeira para $table.$column.
     */
    public function constrained(string $table, string $column = 'id'): self
    {
        $this->references = ['table' => $table, 'column' => $column, 'onDelete' => null, 'onUpdate' => null];

        return $this;
    }

    public function onDelete(string $action): self
    {
        if ($this->references !== null) {
            $this->references['onDelete'] = $this->action($action);
        }

        return $this;
    }

    public function onUpdate(string $action): self
    {
        if ($this->references !== null) {
            $this->references['onUpdate'] = $this->action($action);
        }

        return $this;
    }

    public function cascadeOnDelete(): self
    {
        return $this->onDelete('cascade');
    }

    public function nullOnDelete(): self
    {
        return $this->nullable()->onDelete('set null');
    }

    private function action(string $action): string
    {
        $action = strtolower($action);

        if (! in_array($action, ['cascade', 'restrict', 'set null', 'no action', 'set default'], true)) {
            throw new InvalidArgumentException(sprintf('Ação de chave estrangeira inválida: [%s].', $action));
        }

        return $action;
    }
}
