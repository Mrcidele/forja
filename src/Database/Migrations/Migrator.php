<?php

declare(strict_types=1);

namespace Forja\Database\Migrations;

use Closure;
use Forja\Database\Connection;
use Forja\Database\Schema\Schema;
use RuntimeException;

/**
 * Executa e reverte migrations a partir de um diretório.
 *
 * Cada execução de migrate() forma um lote; rollback() desfaz lotes inteiros.
 */
final readonly class Migrator
{
    private Schema $schema;

    public function __construct(
        private Connection $connection,
        private MigrationRepository $repository,
        private string $directory,
    ) {
        $this->schema = new Schema($connection);
    }

    /**
     * @return list<string> migrations executadas
     */
    public function migrate(): array
    {
        $pending = $this->pending();

        if ($pending === []) {
            return [];
        }

        $batch = $this->repository->lastBatch() + 1;

        foreach ($pending as $name) {
            $migration = $this->resolve($name);
            $this->runInTransaction($migration, function () use ($migration, $name, $batch): void {
                $migration->up($this->schema);
                $this->repository->log($name, $batch);
            });
        }

        return $pending;
    }

    /**
     * @return list<string> migrations revertidas
     */
    public function rollback(int $steps = 1): array
    {
        $this->repository->ensureTable();
        $names = $this->repository->lastBatches($steps);

        foreach ($names as $name) {
            $this->revert($name);
        }

        return $names;
    }

    /**
     * Reverte todas as migrations executadas.
     *
     * @return list<string>
     */
    public function reset(): array
    {
        $this->repository->ensureTable();
        $names = array_reverse(array_keys($this->repository->ran()));

        foreach ($names as $name) {
            $this->revert($name);
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    public function pending(): array
    {
        $this->repository->ensureTable();
        $ran = $this->repository->ran();

        return array_values(array_filter(array_keys($this->files()), static fn (string $name): bool => ! isset($ran[$name])));
    }

    /**
     * @return list<array{migration: string, ran: bool, batch: int|null}>
     */
    public function status(): array
    {
        $this->repository->ensureTable();
        $ran = $this->repository->ran();
        $names = array_unique([...array_keys($this->files()), ...array_keys($ran)]);
        sort($names);

        return array_map(static fn (string $name): array => [
            'migration' => $name,
            'ran' => isset($ran[$name]),
            'batch' => $ran[$name] ?? null,
        ], $names);
    }

    /**
     * @return array<string, string> nome => caminho, em ordem de versão
     */
    public function files(): array
    {
        $files = [];

        foreach (glob(rtrim($this->directory, '/') . '/*.php') ?: [] as $path) {
            $files[basename($path, '.php')] = $path;
        }

        ksort($files);

        return $files;
    }

    private function revert(string $name): void
    {
        $migration = $this->resolve($name);
        $this->runInTransaction($migration, function () use ($migration, $name): void {
            $migration->down($this->schema);
            $this->repository->delete($name);
        });
    }

    private function resolve(string $name): Migration
    {
        $path = $this->files()[$name] ?? throw new RuntimeException(sprintf('Arquivo da migration [%s] não encontrado em [%s].', $name, $this->directory));
        $migration = require $path;

        if (! $migration instanceof Migration) {
            throw new RuntimeException(sprintf('A migration [%s] deve retornar uma instância de %s.', $name, Migration::class));
        }

        return $migration;
    }

    private function runInTransaction(Migration $migration, Closure $callback): void
    {
        if ($migration->withinTransaction) {
            $this->connection->transaction(static fn () => $callback());

            return;
        }

        $callback();
    }
}
