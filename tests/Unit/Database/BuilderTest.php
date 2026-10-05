<?php

declare(strict_types=1);

use Forja\Database\Connection;
use Forja\Database\DatabaseConfig;
use Forja\Database\Query\Builder;

beforeEach(function (): void {
    $this->db = sqliteWithSchema();
    $this->db->table('users')->insert([
        ['name' => 'Ada', 'email_address' => 'ada@forja.test', 'active' => true, 'role' => 'admin'],
        ['name' => 'Linus', 'email_address' => 'linus@forja.test', 'active' => true, 'role' => 'member'],
        ['name' => 'Grace', 'email_address' => 'grace@forja.test', 'active' => false, 'role' => 'member'],
    ]);
    $this->db->table('posts')->insert([
        ['user_id' => 1, 'title' => 'Motores analíticos', 'rating' => 4.5],
        ['user_id' => 1, 'title' => 'Notas', 'rating' => 3.0],
        ['user_id' => 2, 'title' => 'Kernel', 'rating' => 5.0],
    ]);
});

function builderFor(string $driver): Builder
{
    return new Connection(new DatabaseConfig($driver))->table('users');
}

describe('geração de SQL', function (): void {
    it('compila selects completos com parâmetros', function (): void {
        $query = builderFor('sqlite')
            ->select('users.id', 'name as nome')
            ->distinct()
            ->join('posts', 'posts.user_id', '=', 'users.id')
            ->where('active', true)
            ->where('age', '>=', 18)
            ->where(fn (Builder $q): \Forja\Database\Query\Builder => $q->where('role', 'admin')->orWhere('role', 'owner'))
            ->whereIn('id', [1, 2])
            ->whereNotNull('email_address')
            ->whereBetween('created_at', '2026-01-01', '2026-12-31')
            ->groupBy('users.id')
            ->having('total', '>', 1)
            ->orderByDesc('name')
            ->limit(10)
            ->offset(20);

        expect($query->toSql())->toBe(
            'select distinct "users"."id", "name" as "nome" from "users" inner join "posts" on "posts"."user_id" = "users"."id" '
            . 'where "active" = ? and "age" >= ? and ("role" = ? or "role" = ?) and "id" in (?, ?) and "email_address" is not null '
            . 'and "created_at" between ? and ? group by "users"."id" having "total" > ? order by "name" desc limit 10 offset 20',
        )->and($query->getBindings())->toBe([true, 18, 'admin', 'owner', 1, 2, '2026-01-01', '2026-12-31', 1]);
    });

    it('usa a sintaxe de cada banco', function (): void {
        expect(builderFor('mysql')->where('name', 'x')->offset(5)->toSql())
            ->toBe('select * from `users` where `name` = ? limit 18446744073709551615 offset 5')
            ->and(builderFor('pgsql')->where('name', 'x')->offset(5)->toSql())
            ->toBe('select * from "users" where "name" = ? offset 5')
            ->and(builderFor('sqlite')->offset(5)->toSql())
            ->toBe('select * from "users" limit -1 offset 5');
    });

    it('trata condições especiais', function (): void {
        expect(builderFor('sqlite')->where('deleted_at', null)->toSql())->toBe('select * from "users" where "deleted_at" is null')
            ->and(builderFor('sqlite')->where('deleted_at', '!=', null)->toSql())->toBe('select * from "users" where "deleted_at" is not null')
            ->and(builderFor('sqlite')->whereIn('id', [])->toSql())->toBe('select * from "users" where 0 = 1')
            ->and(builderFor('sqlite')->whereNotIn('id', [])->toSql())->toBe('select * from "users" where 1 = 1')
            ->and(builderFor('sqlite')->where(['a' => 1, 'b' => 2])->toSql())->toBe('select * from "users" where "a" = ? and "b" = ?')
            ->and(builderFor('sqlite')->whereColumn('updated_at', '>', 'created_at')->toSql())->toBe('select * from "users" where "updated_at" > "created_at"')
            ->and(builderFor('sqlite')->whereRaw('lower(name) = ?', ['ada'])->getBindings())->toBe(['ada']);
    });

    it('escapa identificadores e bloqueia operadores desconhecidos', function (): void {
        expect(builderFor('sqlite')->select('na"me')->toSql())->toBe('select "na""me" from "users"')
            ->and(fn (): \Forja\Database\Query\Builder => builderFor('sqlite')->where('id', '; drop table users', 1))->toThrow(InvalidArgumentException::class, 'Operador SQL não permitido')
            ->and(fn (): \Forja\Database\Query\Builder => builderFor('sqlite')->orderBy('id', 'sideways'))->toThrow(InvalidArgumentException::class);
    });
});

describe('execução', function (): void {
    it('busca linhas, primeira linha, valor e pluck', function (): void {
        expect($this->db->table('users')->where('active', true)->orderBy('name')->pluck('name'))->toBe(['Ada', 'Linus'])
            ->and($this->db->table('users')->pluck('name', 'id'))->toBe([1 => 'Ada', 2 => 'Linus', 3 => 'Grace'])
            ->and($this->db->table('users')->where('role', 'admin')->first())->toMatchArray(['name' => 'Ada'])
            ->and($this->db->table('users')->find(3))->toMatchArray(['name' => 'Grace'])
            ->and($this->db->table('users')->where('id', 2)->value('email_address'))->toBe('linus@forja.test')
            ->and($this->db->table('users')->where('id', 99)->first())->toBeNull();
    });

    it('faz joins e agregações', function (): void {
        $rows = $this->db->table('users')
            ->select('users.name', $this->db->raw('count(posts.id) as total'))
            ->leftJoin('posts', 'posts.user_id', '=', 'users.id')
            ->groupBy('users.name')
            ->having('total', '>', 0)
            ->orderByDesc('total')
            ->get();

        expect($rows)->toBe([['name' => 'Ada', 'total' => 2], ['name' => 'Linus', 'total' => 1]])
            ->and($this->db->table('posts')->count())->toBe(3)
            ->and($this->db->table('posts')->sum('rating'))->toBe(12.5)
            ->and($this->db->table('posts')->avg('rating'))->toBe(12.5 / 3)
            ->and($this->db->table('posts')->min('rating'))->toBe(3)
            ->and($this->db->table('posts')->max('title'))->toBe('Notas')
            ->and($this->db->table('posts')->where('user_id', 1)->exists())->toBeTrue()
            ->and($this->db->table('posts')->where('user_id', 3)->exists())->toBeFalse()
            ->and($this->db->table('posts')->groupBy('user_id')->count())->toBe(2);
    });

    it('pagina resultados', function (): void {
        $page = $this->db->table('users')->orderBy('id')->paginate(perPage: 2, page: 2);

        expect($page->items)->toHaveCount(1)
            ->and($page->items[0]['name'])->toBe('Grace')
            ->and($page->total)->toBe(3)
            ->and($page->lastPage())->toBe(2)
            ->and($page->hasMorePages())->toBeFalse()
            ->and($page->jsonSerialize()['meta'])->toBe(['total' => 3, 'per_page' => 2, 'current_page' => 2, 'last_page' => 2]);
    });

    it('insere, atualiza, incrementa e remove', function (): void {
        $id = $this->db->table('users')->insertGetId(['name' => 'Alan', 'email_address' => 'alan@forja.test']);

        expect($id)->toBe(4)
            ->and($this->db->table('users')->where('id', $id)->update(['name' => 'Alan Turing']))->toBe(1)
            ->and($this->db->table('users')->find($id)['name'] ?? null)->toBe('Alan Turing');

        $this->db->table('posts')->where('id', 1)->increment('rating', 0.5);

        expect($this->db->table('posts')->find(1)['rating'] ?? null)->toBe(5)
            ->and($this->db->table('users')->where('active', false)->delete())->toBe(1)
            ->and($this->db->table('users')->count())->toBe(3);
    });

    it('exige uma tabela', function (): void {
        new Builder($this->db)->get();
    })->throws(InvalidArgumentException::class, 'Nenhuma tabela');
});
