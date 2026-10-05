<?php

declare(strict_types=1);

use Forja\Database\Connection;
use Forja\Database\DatabaseConfig;
use Forja\Database\Exception\QueryException;
use Forja\Tests\Fixtures\Database\Role;

beforeEach(function (): void {
    $this->db = sqlite();
    $this->db->unprepared('create table items (id integer primary key autoincrement, name text, qty integer, active integer, role text, seen_at text)');
});

it('executa consultas com prepared statements', function (): void {
    $this->db->insert('insert into items (name, qty) values (?, ?)', ['caneta', 3]);
    $this->db->insert('insert into items (name, qty) values (?, ?)', ["d'água", 5]);

    expect($this->db->lastInsertId())->toBe('2')
        ->and($this->db->select('select name, qty from items order by id'))->toBe([['name' => 'caneta', 'qty' => 3], ['name' => "d'água", 'qty' => 5]])
        ->and($this->db->selectOne('select name from items where qty > ?', [4]))->toBe(['name' => "d'água"])
        ->and($this->db->scalar('select count(*) from items'))->toBe(2)
        ->and($this->db->update('update items set qty = qty + 1'))->toBe(2)
        ->and($this->db->delete('delete from items where name = ?', ['caneta']))->toBe(1)
        ->and($this->db->selectOne('select * from items where id = ?', [99]))->toBeNull();
});

it('normaliza booleanos, nulos, datas e enums nos parâmetros', function (): void {
    $this->db->insert(
        'insert into items (name, active, role, seen_at, qty) values (?, ?, ?, ?, ?)',
        ['x', true, Role::Admin, new DateTimeImmutable('2026-10-05 12:30:00'), null],
    );

    expect($this->db->selectOne('select active, role, seen_at, qty from items'))
        ->toBe(['active' => 1, 'role' => 'admin', 'seen_at' => '2026-10-05 12:30:00', 'qty' => null]);
});

it('confirma e desfaz transações', function (): void {
    $this->db->transaction(fn (Connection $db): bool => $db->insert('insert into items (name) values (?)', ['ok']));

    expect(fn () => $this->db->transaction(function (Connection $db): never {
        $db->insert('insert into items (name) values (?)', ['desfeito']);

        throw new RuntimeException('falhou');
    }))->toThrow(RuntimeException::class, 'falhou');

    expect($this->db->select('select name from items'))->toBe([['name' => 'ok']])
        ->and($this->db->transactionLevel())->toBe(0);
});

it('aninha transações com savepoints', function (): void {
    $this->db->transaction(function (Connection $db): void {
        $db->insert('insert into items (name) values (?)', ['externa']);

        try {
            $db->transaction(function (Connection $db): never {
                $db->insert('insert into items (name) values (?)', ['interna']);

                throw new RuntimeException();
            });
        } catch (RuntimeException) {
        }

        expect($db->transactionLevel())->toBe(1);
    });

    expect($this->db->select('select name from items'))->toBe([['name' => 'externa']]);
});

it('inclui o SQL nas falhas de consulta', function (): void {
    try {
        $this->db->select('select * from tabela_inexistente where id = ?', [1]);
        $this->fail('Deveria falhar.');
    } catch (QueryException $exception) {
        expect($exception->getMessage())->toContain('tabela_inexistente')
            ->and($exception->sql)->toBe('select * from tabela_inexistente where id = ?')
            ->and($exception->bindings)->toBe([1])
            ->and($exception->getPrevious())->toBeInstanceOf(PDOException::class);
    }
});

it('notifica ouvintes a cada consulta', function (): void {
    $queries = [];
    $this->db->listen(function (string $sql, array $bindings, float $time) use (&$queries): void {
        $queries[] = [$sql, $bindings, $time >= 0];
    });

    $this->db->select('select * from items where id = ?', [1]);

    expect($queries)->toBe([['select * from items where id = ?', [1], true]]);
});

it('conecta sob demanda e reconecta após desconectar', function (): void {
    $connection = new Connection(new DatabaseConfig('sqlite', ':memory:'));
    $first = $connection->pdo();
    $connection->disconnect();

    expect($connection->pdo())->not->toBe($first)
        ->and($connection->driver())->toBe('sqlite');
});

it('monta DSNs por driver e valida a configuração', function (): void {
    expect(DatabaseConfig::fromArray(['driver' => 'mysql', 'database' => 'app', 'host' => 'db', 'port' => '3307'])->dsn())
        ->toBe('mysql:host=db;port=3307;dbname=app;charset=utf8mb4')
        ->and(DatabaseConfig::fromArray(['driver' => 'pgsql', 'database' => 'app'])->dsn())
        ->toBe("pgsql:host=127.0.0.1;port=5432;dbname=app;options='--client_encoding=utf8'")
        ->and(DatabaseConfig::fromArray(['database' => '/tmp/app.sqlite'])->dsn())->toBe('sqlite:/tmp/app.sqlite')
        ->and(fn (): \Forja\Database\DatabaseConfig => DatabaseConfig::fromArray(['driver' => 'oracle']))->toThrow(InvalidArgumentException::class, 'oracle');
});
