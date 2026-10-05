<?php

declare(strict_types=1);

namespace Forja\Database\Migrations;

use Forja\Database\Connection;
use Forja\Database\Schema\Blueprint;
use Forja\Database\Schema\Schema;

/**
 * Tabela de controle com as migrations executadas e o lote de cada uma.
 */
final readonly class MigrationRepository
{
    public function __construct(
        private Connection $connection,
        private string $table = 'migrations',
    ) {
    }

    public function ensureTable(): void
    {
        $schema = new Schema($this->connection);

        if ($schema->hasTable($this->table)) {
            return;
        }

        $schema->create($this->table, static function (Blueprint $table): void {
            $table->id();
            $table->string('migration')->unique();
            $table->integer('batch');
            $table->dateTime('executed_at');
        });
    }

    /**
     * @return array<string, int> migration => lote
     */
    public function ran(): array
    {
        $ran = [];

        foreach ($this->connection->table($this->table)->orderBy('id')->get() as $row) {
            if (is_string($row['migration']) && is_numeric($row['batch'])) {
                $ran[$row['migration']] = (int) $row['batch'];
            }
        }

        return $ran;
    }

    public function lastBatch(): int
    {
        $batch = $this->connection->table($this->table)->max('batch');

        return is_numeric($batch) ? (int) $batch : 0;
    }

    /**
     * Migrations dos últimos $batches lotes, da mais recente para a mais antiga.
     *
     * @return list<string>
     */
    public function lastBatches(int $batches): array
    {
        $from = $this->lastBatch() - max(1, $batches) + 1;

        return array_values(array_filter(
            $this->connection->table($this->table)->where('batch', '>=', $from)->orderByDesc('id')->pluck('migration'),
            is_string(...),
        ));
    }

    public function log(string $migration, int $batch): void
    {
        $this->connection->table($this->table)->insert([
            'migration' => $migration,
            'batch' => $batch,
            'executed_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $migration): void
    {
        $this->connection->table($this->table)->where('migration', $migration)->delete();
    }
}
