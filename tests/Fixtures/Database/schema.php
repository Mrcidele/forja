<?php

declare(strict_types=1);

use Forja\Database\Schema\Blueprint;
use Forja\Database\Schema\Schema;

/**
 * Cria as tabelas usadas pelos testes de banco.
 */
return static function (Schema $schema): void {
    $schema->create('users', static function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email_address')->unique();
        $table->boolean('active')->default(true);
        $table->string('role', 20)->default('member');
        $table->json('settings')->nullable();
        $table->dateTime('created_at')->nullable();
    });

    $schema->create('posts', static function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->string('title');
        $table->decimal('rating', 3, 1)->default(0);
    });

    $schema->create('tags', static function (Blueprint $table): void {
        $table->string('slug', 50)->primary();
        $table->string('label');
    });
};
