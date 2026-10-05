<?php

declare(strict_types=1);

use Forja\Database\Migrations\MigrationRepository;
use Forja\Database\Migrations\Migrator;
use Forja\Database\Schema\Schema;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir() . '/forja-migrations-' . bin2hex(random_bytes(4));
    copyDirectory(dirname(__DIR__, 2) . '/Fixtures/Migrations', $this->directory);
    $this->db = sqlite();
    $this->schema = new Schema($this->db);
    $this->migrator = new Migrator($this->db, new MigrationRepository($this->db), $this->directory);
});

afterEach(function (): void {
    removeDirectory($this->directory);
});

function addMigration(string $directory, string $name, string $table): void
{
    file_put_contents($directory . '/' . $name . '.php', <<<PHP
        <?php

        use Forja\Database\Migrations\Migration;
        use Forja\Database\Schema\Blueprint;
        use Forja\Database\Schema\Schema;

        return new class () extends Migration {
            public function up(Schema \$schema): void
            {
                \$schema->create('{$table}', fn (Blueprint \$t) => \$t->id());
            }

            public function down(Schema \$schema): void
            {
                \$schema->drop('{$table}');
            }
        };
        PHP);
}

it('executa as migrations pendentes em ordem e registra o lote', function (): void {
    expect($this->migrator->pending())->toBe(['2026_01_01_000000_create_authors_table', '2026_01_02_000000_create_books_table'])
        ->and($this->migrator->migrate())->toBe(['2026_01_01_000000_create_authors_table', '2026_01_02_000000_create_books_table'])
        ->and($this->schema->hasTable('books'))->toBeTrue()
        ->and($this->migrator->pending())->toBe([])
        ->and($this->migrator->migrate())->toBe([])
        ->and($this->db->table('migrations')->pluck('batch'))->toBe([1, 1]);
});

it('desfaz o último lote no rollback', function (): void {
    $this->migrator->migrate();
    addMigration($this->directory, '2026_02_01_000000_create_reviews_table', 'reviews');
    $this->migrator->migrate();

    expect($this->migrator->rollback())->toBe(['2026_02_01_000000_create_reviews_table'])
        ->and($this->schema->hasTable('reviews'))->toBeFalse()
        ->and($this->schema->hasTable('books'))->toBeTrue()
        ->and($this->migrator->rollback())->toBe(['2026_01_02_000000_create_books_table', '2026_01_01_000000_create_authors_table'])
        ->and($this->schema->hasTable('authors'))->toBeFalse()
        ->and($this->migrator->rollback())->toBe([]);
});

it('desfaz vários lotes e reseta tudo', function (): void {
    $this->migrator->migrate();
    addMigration($this->directory, '2026_02_01_000000_create_reviews_table', 'reviews');
    $this->migrator->migrate();

    expect($this->migrator->rollback(steps: 2))->toHaveCount(3);

    $this->migrator->migrate();

    expect($this->migrator->reset())->toBe([
        '2026_02_01_000000_create_reviews_table',
        '2026_01_02_000000_create_books_table',
        '2026_01_01_000000_create_authors_table',
    ]);
});

it('informa o status de cada migration', function (): void {
    $this->migrator->migrate();
    addMigration($this->directory, '2026_02_01_000000_create_reviews_table', 'reviews');

    expect($this->migrator->status())->toBe([
        ['migration' => '2026_01_01_000000_create_authors_table', 'ran' => true, 'batch' => 1],
        ['migration' => '2026_01_02_000000_create_books_table', 'ran' => true, 'batch' => 1],
        ['migration' => '2026_02_01_000000_create_reviews_table', 'ran' => false, 'batch' => null],
    ]);
});

it('desfaz a migration que falha sem registrá-la', function (): void {
    file_put_contents($this->directory . '/2026_03_01_000000_broken.php', <<<'PHP'
        <?php

        use Forja\Database\Migrations\Migration;
        use Forja\Database\Schema\Blueprint;
        use Forja\Database\Schema\Schema;

        return new class () extends Migration {
            public function up(Schema $schema): void
            {
                $schema->create('half', fn (Blueprint $t) => $t->id());
                throw new RuntimeException('quebrou');
            }

            public function down(Schema $schema): void
            {
            }
        };
        PHP);

    expect(fn () => $this->migrator->migrate())->toThrow(RuntimeException::class, 'quebrou')
        ->and($this->schema->hasTable('half'))->toBeFalse()
        ->and($this->migrator->pending())->toBe(['2026_03_01_000000_broken']);
});

it('exige que o arquivo retorne uma Migration', function (): void {
    file_put_contents($this->directory . '/2026_04_01_000000_invalid.php', '<?php return 42;');
    $this->migrator->migrate();
})->throws(RuntimeException::class, 'deve retornar uma instância');
