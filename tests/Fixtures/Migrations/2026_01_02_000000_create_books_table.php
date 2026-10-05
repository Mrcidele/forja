<?php

declare(strict_types=1);

use Forja\Database\Migrations\Migration;
use Forja\Database\Schema\Blueprint;
use Forja\Database\Schema\Schema;

return new class () extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('books', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->constrained('authors');
            $table->string('title');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('books');
    }
};
