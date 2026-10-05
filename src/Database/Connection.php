<?php

declare(strict_types=1);

namespace Forja\Database;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Forja\Container\ResettableInterface;
use Forja\Database\Exception\QueryException;
use Forja\Database\Query\Builder;
use Forja\Database\Query\Grammar\Grammar;
use Forja\Database\Query\Grammar\MySqlGrammar;
use Forja\Database\Query\Grammar\PostgresGrammar;
use Forja\Database\Query\Grammar\SqliteGrammar;
use PDO;
use PDOException;
use PDOStatement;
use Stringable;
use Throwable;

/**
 * Conexão com o banco sobre PDO: prepared statements, transações (com
 * savepoints para aninhamento) e ponto de partida do query builder.
 *
 * O PDO é criado na primeira consulta.
 */
final class Connection implements ResettableInterface
{
    private ?PDO $pdo;

    private int $transactions = 0;

    private readonly Grammar $grammar;

    /** @var list<Closure(string, list<mixed>, float): void> */
    private array $listeners = [];

    public function __construct(
        private readonly DatabaseConfig $config,
        ?PDO $pdo = null,
    ) {
        $this->pdo = $pdo;
        $this->grammar = match ($config->driver) {
            'mysql' => new MySqlGrammar(),
            'pgsql' => new PostgresGrammar(),
            'sqlite' => new SqliteGrammar(),
        };
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= $this->connect();
    }

    public function driver(): string
    {
        return $this->config->driver;
    }

    public function grammar(): Grammar
    {
        return $this->grammar;
    }

    public function table(string|Expression $table): Builder
    {
        return new Builder($this)->from($table);
    }

    public function raw(string $sql): Expression
    {
        return new Expression($sql);
    }

    /**
     * @param list<mixed> $bindings
     *
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings, function (string $sql, array $bindings): array {
            /** @var list<array<string, mixed>> */
            return $this->execute($sql, $bindings)->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    /**
     * @param list<mixed> $bindings
     *
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        return $this->select($sql, $bindings)[0] ?? null;
    }

    /**
     * Primeira coluna da primeira linha.
     *
     * @param list<mixed> $bindings
     */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        $row = $this->selectOne($sql, $bindings);

        return $row === null ? null : reset($row);
    }

    /**
     * @param list<mixed> $bindings
     */
    public function insert(string $sql, array $bindings = []): bool
    {
        return $this->statement($sql, $bindings);
    }

    /**
     * @param list<mixed> $bindings
     *
     * @return int linhas afetadas
     */
    public function update(string $sql, array $bindings = []): int
    {
        return $this->affectingStatement($sql, $bindings);
    }

    /**
     * @param list<mixed> $bindings
     *
     * @return int linhas afetadas
     */
    public function delete(string $sql, array $bindings = []): int
    {
        return $this->affectingStatement($sql, $bindings);
    }

    /**
     * @param list<mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): bool
    {
        return $this->run($sql, $bindings, function (string $sql, array $bindings): bool {
            $this->execute($sql, $bindings);

            return true;
        });
    }

    /**
     * @param list<mixed> $bindings
     */
    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings, fn (string $sql, array $bindings): int => $this->execute($sql, $bindings)->rowCount());
    }

    /**
     * Executa SQL sem preparar (permite vários comandos de uma vez). Não use com dados externos.
     */
    public function unprepared(string $sql): bool
    {
        return $this->run($sql, [], fn (string $sql): bool => $this->pdo()->exec($sql) !== false);
    }

    public function lastInsertId(?string $sequence = null): string
    {
        return (string) $this->pdo()->lastInsertId($sequence);
    }

    /**
     * Executa a closure numa transação: confirma no sucesso e desfaz em
     * caso de exceção, que é relançada.
     *
     * @template T
     *
     * @param Closure(self): T $callback
     *
     * @return T
     */
    public function transaction(Closure $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->rollBack();

            throw $exception;
        }
    }

    public function beginTransaction(): void
    {
        if ($this->transactions === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT trans' . ($this->transactions + 1));
        }

        $this->transactions++;
    }

    public function commit(): void
    {
        if ($this->transactions === 1) {
            $this->pdo()->commit();
        } elseif ($this->transactions > 1) {
            $this->pdo()->exec('RELEASE SAVEPOINT trans' . $this->transactions);
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    public function rollBack(): void
    {
        if ($this->transactions === 1) {
            $this->pdo()->rollBack();
        } elseif ($this->transactions > 1) {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT trans' . $this->transactions);
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    public function transactionLevel(): int
    {
        return $this->transactions;
    }

    /**
     * Registra um ouvinte chamado após cada consulta com SQL, valores e tempo em ms.
     *
     * @param Closure(string, list<mixed>, float): void $listener
     */
    public function listen(Closure $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * Desfaz transações deixadas abertas por uma requisição que falhou,
     * mantendo a conexão para a próxima (worker mode).
     */
    public function reset(): void
    {
        if ($this->pdo instanceof \PDO && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        $this->transactions = 0;
    }

    /**
     * Fecha a conexão; a próxima consulta reconecta.
     */
    public function disconnect(): void
    {
        $this->pdo = null;
        $this->transactions = 0;
    }

    private function connect(): PDO
    {
        $pdo = new PDO($this->config->dsn(), $this->config->username, $this->config->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ] + $this->config->options);

        if ($this->config->driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        return $pdo;
    }

    /**
     * @template T
     *
     * @param list<mixed> $bindings
     * @param Closure(string, list<mixed>): T $callback
     *
     * @return T
     */
    private function run(string $sql, array $bindings, Closure $callback): mixed
    {
        $start = hrtime(true);

        try {
            $result = $callback($sql, $bindings);
        } catch (PDOException $exception) {
            throw new QueryException($sql, $bindings, $exception);
        }

        $elapsed = (hrtime(true) - $start) / 1_000_000;

        foreach ($this->listeners as $listener) {
            $listener($sql, $bindings, $elapsed);
        }

        return $result;
    }

    /**
     * @param list<mixed> $bindings
     */
    private function execute(string $sql, array $bindings): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);

        foreach ($bindings as $index => $value) {
            [$value, $type] = $this->normalize($value);
            $statement->bindValue($index + 1, $value, $type);
        }

        $statement->execute();

        return $statement;
    }

    /**
     * @return array{mixed, int}
     */
    private function normalize(mixed $value): array
    {
        return match (true) {
            $value === null => [null, PDO::PARAM_NULL],
            is_int($value) => [$value, PDO::PARAM_INT],
            is_bool($value) => $this->config->driver === 'pgsql' ? [$value, PDO::PARAM_BOOL] : [$value ? 1 : 0, PDO::PARAM_INT],
            $value instanceof BackedEnum => $this->normalize($value->value),
            $value instanceof DateTimeInterface => [$value->format('Y-m-d H:i:s'), PDO::PARAM_STR],
            is_float($value), is_string($value), $value instanceof Stringable => [(string) $value, PDO::PARAM_STR],
            default => [json_encode($value, JSON_THROW_ON_ERROR), PDO::PARAM_STR],
        };
    }
}
