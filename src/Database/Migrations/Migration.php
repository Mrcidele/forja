<?php

declare(strict_types=1);

namespace Forja\Database\Migrations;

use Forja\Database\Schema\Schema;

/**
 * Migration versionada pelo nome do arquivo (ex.: 2026_10_05_120000_create_users_table.php),
 * que deve retornar uma instância desta classe.
 */
abstract class Migration
{
    /**
     * Executa a migration dentro de uma transação (quando o banco permite DDL transacional).
     */
    public bool $withinTransaction = true;

    abstract public function up(Schema $schema): void;

    abstract public function down(Schema $schema): void;
}
