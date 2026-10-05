<?php

declare(strict_types=1);

namespace Forja\Database\Query;

use Closure;
use Forja\Database\Connection;
use Forja\Database\Expression;
use Forja\Database\Query\Grammar\Grammar;
use InvalidArgumentException;

/**
 * Query builder fluente. Valores sempre viram parâmetros (?) e
 * identificadores são escapados pela gramática do banco.
 *
 * @phpstan-type Where array{type: 'basic', boolean: string, column: string|Expression, operator: string, value: mixed}
 *     |array{type: 'in', boolean: string, column: string|Expression, values: list<mixed>, not: bool}
 *     |array{type: 'null', boolean: string, column: string|Expression, not: bool}
 *     |array{type: 'between', boolean: string, column: string|Expression, values: array{mixed, mixed}, not: bool}
 *     |array{type: 'column', boolean: string, first: string, operator: string, second: string}
 *     |array{type: 'nested', boolean: string, query: Builder}
 *     |array{type: 'raw', boolean: string, sql: string, bindings: list<mixed>}
 */
final class Builder
{
    private const array OPERATORS = ['=', '<', '>', '<=', '>=', '<>', '!=', 'like', 'not like', 'ilike', 'not ilike'];

    private string|Expression $table = '';

    /** @var list<string|Expression> */
    private array $columns = ['*'];

    private bool $distinct = false;

    /** @var list<array{type: string, table: string|Expression, first: string, operator: string, second: string}> */
    private array $joins = [];

    /** @var list<Where> */
    private array $wheres = [];

    /** @var list<string|Expression> */
    private array $groups = [];

    /** @var list<array{boolean: string, column: string|Expression, operator: string, value: mixed}> */
    private array $havings = [];

    /** @var list<array{column: string|Expression, direction: string}> */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function newQuery(): self
    {
        return new self($this->connection);
    }

    public function from(string|Expression $table): self
    {
        $this->table = $table;

        return $this;
    }

    public function select(string|Expression ...$columns): self
    {
        $this->columns = $columns === [] ? ['*'] : array_values($columns);

        return $this;
    }

    public function addSelect(string|Expression ...$columns): self
    {
        $current = $this->columns === ['*'] ? [] : $this->columns;
        $this->columns = [...$current, ...array_values($columns)];

        return $this;
    }

    public function distinct(bool $distinct = true): self
    {
        $this->distinct = $distinct;

        return $this;
    }

    public function join(string|Expression $table, string $first, string $operator, string $second, string $type = 'inner'): self
    {
        $this->joins[] = ['type' => $type, 'table' => $table, 'first' => $first, 'operator' => $this->operator($operator), 'second' => $second];

        return $this;
    }

    public function leftJoin(string|Expression $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin(string|Expression $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    /**
     * where('ativo', true), where('idade', '>=', 18) ou where(fn ($q) => ...) para agrupar.
     *
     * @param string|Expression|(Closure(self): mixed)|array<string, mixed> $column
     */
    public function where(string|Expression|Closure|array $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): self
    {
        if (is_array($column)) {
            foreach ($column as $key => $item) {
                $this->where($key, '=', $item, $boolean);
            }

            return $this;
        }

        if ($column instanceof Closure) {
            $nested = $this->newQuery()->from($this->table);
            $column($nested);
            $this->wheres[] = ['type' => 'nested', 'boolean' => $boolean, 'query' => $nested];

            return $this;
        }

        if (func_num_args() === 2) {
            [$operator, $value] = ['=', $operator];
        }

        if (! is_string($operator)) {
            throw new InvalidArgumentException('O operador da condição deve ser uma string.');
        }

        if ($value === null && in_array($operator, ['=', '!=', '<>'], true)) {
            return $this->whereNull($column, $boolean, $operator !== '=');
        }

        $this->wheres[] = ['type' => 'basic', 'boolean' => $boolean, 'column' => $column, 'operator' => $this->operator($operator), 'value' => $value];

        return $this;
    }

    /**
     * @param string|Expression|(Closure(self): mixed)|array<string, mixed> $column
     */
    public function orWhere(string|Expression|Closure|array $column, mixed $operator = null, mixed $value = null): self
    {
        return func_num_args() === 2
            ? $this->where($column, '=', $operator, 'or')
            : $this->where($column, $operator, $value, 'or');
    }

    /**
     * Compara duas colunas: whereColumn('updated_at', '>', 'created_at').
     */
    public function whereColumn(string $first, string $operator, string $second, string $boolean = 'and'): self
    {
        $this->wheres[] = ['type' => 'column', 'boolean' => $boolean, 'first' => $first, 'operator' => $this->operator($operator), 'second' => $second];

        return $this;
    }

    /**
     * @param iterable<mixed> $values
     */
    public function whereIn(string|Expression $column, iterable $values, string $boolean = 'and', bool $not = false): self
    {
        $list = [];

        foreach ($values as $value) {
            $list[] = $value;
        }

        $this->wheres[] = ['type' => 'in', 'boolean' => $boolean, 'column' => $column, 'values' => $list, 'not' => $not];

        return $this;
    }

    /**
     * @param iterable<mixed> $values
     */
    public function whereNotIn(string|Expression $column, iterable $values, string $boolean = 'and'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * @param iterable<mixed> $values
     */
    public function orWhereIn(string|Expression $column, iterable $values): self
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function whereNull(string|Expression $column, string $boolean = 'and', bool $not = false): self
    {
        $this->wheres[] = ['type' => 'null', 'boolean' => $boolean, 'column' => $column, 'not' => $not];

        return $this;
    }

    public function whereNotNull(string|Expression $column, string $boolean = 'and'): self
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function orWhereNull(string|Expression $column): self
    {
        return $this->whereNull($column, 'or');
    }

    public function whereBetween(string|Expression $column, mixed $from, mixed $to, string $boolean = 'and', bool $not = false): self
    {
        $this->wheres[] = ['type' => 'between', 'boolean' => $boolean, 'column' => $column, 'values' => [$from, $to], 'not' => $not];

        return $this;
    }

    public function whereNotBetween(string|Expression $column, mixed $from, mixed $to, string $boolean = 'and'): self
    {
        return $this->whereBetween($column, $from, $to, $boolean, true);
    }

    /**
     * @param list<mixed> $bindings
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): self
    {
        $this->wheres[] = ['type' => 'raw', 'boolean' => $boolean, 'sql' => $sql, 'bindings' => $bindings];

        return $this;
    }

    public function groupBy(string|Expression ...$columns): self
    {
        $this->groups = [...$this->groups, ...array_values($columns)];

        return $this;
    }

    public function having(string|Expression $column, string $operator, mixed $value, string $boolean = 'and'): self
    {
        $this->havings[] = ['boolean' => $boolean, 'column' => $column, 'operator' => $this->operator($operator), 'value' => $value];

        return $this;
    }

    public function orderBy(string|Expression $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction);

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException(sprintf('Direção de ordenação inválida: [%s].', $direction));
        }

        $this->orders[] = ['column' => $column, 'direction' => $direction];

        return $this;
    }

    public function orderByDesc(string|Expression $column): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function limit(?int $limit): self
    {
        $this->limit = $limit === null ? null : max(0, $limit);

        return $this;
    }

    public function offset(?int $offset): self
    {
        $this->offset = $offset === null ? null : max(0, $offset);

        return $this;
    }

    public function forPage(int $page, int $perPage): self
    {
        return $this->offset((max(1, $page) - 1) * $perPage)->limit($perPage);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        return $this->connection->select($this->toSql(), $this->getBindings());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        return (clone $this)->limit(1)->get()[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int|string $id, string $column = 'id'): ?array
    {
        return (clone $this)->where($column, '=', $id)->first();
    }

    public function value(string|Expression $column): mixed
    {
        $row = (clone $this)->select($column)->first();

        return $row === null ? null : reset($row);
    }

    /**
     * Valores de uma coluna, opcionalmente indexados por outra.
     *
     * @return array<array-key, mixed>
     */
    public function pluck(string $column, ?string $key = null): array
    {
        $columns = $key === null ? [$column] : [$column, $key];
        $rows = (clone $this)->select(...$columns)->get();
        $valueKey = $this->alias($column);
        $indexKey = $key === null ? null : $this->alias($key);
        $result = [];

        foreach ($rows as $row) {
            $value = $row[$valueKey] ?? null;
            $index = $indexKey === null ? null : ($row[$indexKey] ?? null);

            if (is_int($index) || is_string($index)) {
                $result[$index] = $value;
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    public function count(string $column = '*'): int
    {
        return (int) $this->numeric($this->aggregate('count', $column));
    }

    public function exists(): bool
    {
        return (clone $this)->select(new Expression('1'))->first() !== null;
    }

    public function sum(string $column): int|float
    {
        return $this->numeric($this->aggregate('sum', $column));
    }

    public function avg(string $column): ?float
    {
        $value = $this->aggregate('avg', $column);

        return $value === null ? null : (float) $this->numeric($value);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('min', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('max', $column);
    }

    /**
     * Página de resultados com o total de registros.
     *
     * @return Paginator<array<string, mixed>>
     */
    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $total = (clone $this)->count();
        $items = (clone $this)->forPage($page, $perPage)->get();

        return new Paginator($items, $total, $perPage, $page);
    }

    /**
     * Insere uma linha ou várias (lista de arrays com as mesmas colunas).
     *
     * @param array<string, mixed>|list<array<string, mixed>> $values
     */
    public function insert(array $values): bool
    {
        if ($values === []) {
            return true;
        }

        $rows = array_is_list($values) ? $values : [$values];
        /** @var list<array<string, mixed>> $rows */
        $columns = array_keys($rows[0]);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $bindings = [];

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column] ?? null;
            }
        }

        $sql = sprintf(
            'insert into %s (%s) values %s',
            $this->wrapTable(),
            $this->grammar()->columnize($columns),
            implode(', ', array_fill(0, count($rows), $placeholders)),
        );

        return $this->connection->insert($sql, $bindings);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insertGetId(array $values, string $key = 'id'): int|string
    {
        $columns = array_keys($values);
        $sql = sprintf(
            'insert into %s (%s) values (%s)',
            $this->wrapTable(),
            $this->grammar()->columnize($columns),
            implode(', ', array_fill(0, count($columns), '?')),
        );

        if ($this->grammar()->supportsReturning()) {
            $id = $this->connection->scalar($sql . ' returning ' . $this->grammar()->wrap($key), array_values($values));
        } else {
            $this->connection->insert($sql, array_values($values));
            $id = $this->connection->lastInsertId();
        }

        return is_numeric($id) ? (int) $id : (string) (is_scalar($id) ? $id : '');
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return int linhas afetadas
     */
    public function update(array $values): int
    {
        $sets = [];
        $setBindings = [];

        foreach ($values as $column => $value) {
            if ($value instanceof Expression) {
                $sets[] = $this->grammar()->wrap($column) . ' = ' . $value->value;

                continue;
            }

            $sets[] = $this->grammar()->wrap($column) . ' = ?';
            $setBindings[] = $value;
        }

        [$where, $bindings] = $this->compileWheres();
        $sql = trim(sprintf('update %s set %s %s', $this->wrapTable(), implode(', ', $sets), $where));

        return $this->connection->update($sql, [...$setBindings, ...$bindings]);
    }

    public function increment(string $column, int|float $amount = 1): int
    {
        return $this->update([$column => new Expression($this->grammar()->wrap($column) . ' + ' . $amount)]);
    }

    /**
     * @return int linhas afetadas
     */
    public function delete(): int
    {
        [$where, $bindings] = $this->compileWheres();

        return $this->connection->delete(trim(sprintf('delete from %s %s', $this->wrapTable(), $where)), $bindings);
    }

    public function toSql(): string
    {
        [$where] = $this->compileWheres();
        [$having] = $this->compileHavings();

        $parts = [
            ($this->distinct ? 'select distinct ' : 'select ') . $this->grammar()->columnize($this->columns),
            'from ' . $this->wrapTable(),
            $this->compileJoins(),
            $where,
            $this->groups === [] ? '' : 'group by ' . $this->grammar()->columnize($this->groups),
            $having,
            $this->compileOrders(),
            $this->grammar()->compileLimit($this->limit, $this->offset),
        ];

        return implode(' ', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * @return list<mixed>
     */
    public function getBindings(): array
    {
        [, $whereBindings] = $this->compileWheres();
        [, $havingBindings] = $this->compileHavings();

        return [...$whereBindings, ...$havingBindings];
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Condições compiladas sem o "where" inicial; usado em grupos aninhados.
     *
     * @return array{string, list<mixed>}
     */
    public function compileConditions(): array
    {
        $sql = '';
        $bindings = [];

        foreach ($this->wheres as $index => $where) {
            [$clause, $clauseBindings] = $this->compileWhere($where);
            $sql .= ($index === 0 ? '' : ' ' . $where['boolean'] . ' ') . $clause;
            $bindings = [...$bindings, ...$clauseBindings];
        }

        return [$sql, $bindings];
    }

    /**
     * @return array{string, list<mixed>}
     */
    private function compileWheres(): array
    {
        [$sql, $bindings] = $this->compileConditions();

        return [$sql === '' ? '' : 'where ' . $sql, $bindings];
    }

    /**
     * @param Where $where
     *
     * @return array{string, list<mixed>}
     */
    private function compileWhere(array $where): array
    {
        $grammar = $this->grammar();

        return match ($where['type']) {
            'basic' => $this->compileComparison($where['column'], $where['operator'], $where['value']),
            'column' => [$grammar->wrap($where['first']) . ' ' . $where['operator'] . ' ' . $grammar->wrap($where['second']), []],
            'in' => $where['values'] === []
                ? [$where['not'] ? '1 = 1' : '0 = 1', []]
                : [$grammar->wrap($where['column']) . ($where['not'] ? ' not in (' : ' in (') . implode(', ', array_fill(0, count($where['values']), '?')) . ')', $where['values']],
            'null' => [$grammar->wrap($where['column']) . ($where['not'] ? ' is not null' : ' is null'), []],
            'between' => [$grammar->wrap($where['column']) . ($where['not'] ? ' not between ? and ?' : ' between ? and ?'), [$where['values'][0], $where['values'][1]]],
            'nested' => $this->compileNested($where['query']),
            'raw' => [$where['sql'], $where['bindings']],
        };
    }

    /**
     * @return array{string, list<mixed>}
     */
    private function compileNested(self $query): array
    {
        [$sql, $bindings] = $query->compileConditions();

        return $sql === '' ? ['1 = 1', []] : ['(' . $sql . ')', $bindings];
    }

    /**
     * @return array{string, list<mixed>}
     */
    private function compileComparison(string|Expression $column, string $operator, mixed $value): array
    {
        if ($value instanceof Expression) {
            return [$this->grammar()->wrap($column) . ' ' . $operator . ' ' . $value->value, []];
        }

        return [$this->grammar()->wrap($column) . ' ' . $operator . ' ?', [$value]];
    }

    /**
     * @return array{string, list<mixed>}
     */
    private function compileHavings(): array
    {
        $sql = '';
        $bindings = [];

        foreach ($this->havings as $index => $having) {
            [$clause, $clauseBindings] = $this->compileComparison($having['column'], $having['operator'], $having['value']);
            $sql .= ($index === 0 ? '' : ' ' . $having['boolean'] . ' ') . $clause;
            $bindings = [...$bindings, ...$clauseBindings];
        }

        return [$sql === '' ? '' : 'having ' . $sql, $bindings];
    }

    private function compileJoins(): string
    {
        $grammar = $this->grammar();

        return implode(' ', array_map(
            static fn (array $join): string => sprintf(
                '%s join %s on %s %s %s',
                $join['type'],
                $grammar->wrap($join['table']),
                $grammar->wrap($join['first']),
                $join['operator'],
                $grammar->wrap($join['second']),
            ),
            $this->joins,
        ));
    }

    private function compileOrders(): string
    {
        if ($this->orders === []) {
            return '';
        }

        return 'order by ' . implode(', ', array_map(
            fn (array $order): string => $this->grammar()->wrap($order['column']) . ' ' . $order['direction'],
            $this->orders,
        ));
    }

    private function aggregate(string $function, string $column): mixed
    {
        $query = clone $this;
        $query->orders = [];
        $query->limit = null;
        $query->offset = null;
        $expression = $column === '*' ? '*' : $this->grammar()->wrap($column);

        if ($query->groups !== [] || $query->distinct) {
            // Agrega sobre a consulta agrupada como subconsulta.
            $sql = sprintf('select %s(*) as aggregate from (%s) as aggregate_table', $function, $query->toSql());

            return $this->connection->scalar($sql, $query->getBindings());
        }

        $query->columns = [new Expression(sprintf('%s(%s) as aggregate', $function, $expression))];

        return $this->connection->scalar($query->toSql(), $query->getBindings());
    }

    private function numeric(mixed $value): int|float
    {
        return match (true) {
            is_int($value), is_float($value) => $value,
            is_numeric($value) => str_contains($value, '.') ? (float) $value : (int) $value,
            default => 0,
        };
    }

    private function operator(string $operator): string
    {
        $normalized = strtolower(trim($operator));

        if (! in_array($normalized, self::OPERATORS, true)) {
            throw new InvalidArgumentException(sprintf('Operador SQL não permitido: [%s].', $operator));
        }

        return $normalized;
    }

    private function alias(string $column): string
    {
        if (preg_match('/\s+as\s+(.+)$/i', $column, $matches) === 1) {
            return trim($matches[1]);
        }

        $segments = explode('.', $column);

        return $segments[array_key_last($segments)];
    }

    private function wrapTable(): string
    {
        if ($this->table === '') {
            throw new InvalidArgumentException('Nenhuma tabela informada para a consulta.');
        }

        return $this->grammar()->wrap($this->table);
    }

    private function grammar(): Grammar
    {
        return $this->connection->grammar();
    }
}
