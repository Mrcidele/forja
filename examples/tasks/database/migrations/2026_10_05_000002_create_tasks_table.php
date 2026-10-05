<?php

declare(strict_types=1);

use Forja\Database\Migrations\Migration;
use Forja\Database\Schema\Blueprint;
use Forja\Database\Schema\Schema;

return new class () extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('tasks', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('status', 20)->default('pendente');
            $table->date('due_date')->nullable();
            $table->dateTime('created_at');
            $table->index('status');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('tasks');
    }
};
