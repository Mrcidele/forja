<?php

declare(strict_types=1);

use Forja\Database\ORM\EntityManager;
use Forja\Database\ORM\MappingException;
use Forja\Database\ORM\MetadataFactory;
use Forja\Tests\Fixtures\Database\Post;
use Forja\Tests\Fixtures\Database\Role;
use Forja\Tests\Fixtures\Database\Tag;
use Forja\Tests\Fixtures\Database\Unmapped;
use Forja\Tests\Fixtures\Database\User;
use Forja\Tests\Fixtures\Database\UserRepository;

beforeEach(function (): void {
    $this->db = sqliteWithSchema();
    $this->em = new EntityManager($this->db);
});

function seedUsers(EntityManager $em): array
{
    $ada = new User('Ada', 'ada@forja.test', role: Role::Admin, settings: ['tema' => 'escuro'], createdAt: new DateTimeImmutable('2026-01-02 10:00:00'));
    $linus = new User('Linus', 'linus@forja.test');
    $grace = new User('Grace', 'grace@forja.test', active: false);

    foreach ([$ada, $linus, $grace] as $user) {
        $em->save($user);
    }

    $em->save(new Post((int) $ada->id, 'Motores analíticos', 4.5));
    $em->save(new Post((int) $ada->id, 'Notas', 3.0));
    $em->save(new Post((int) $linus->id, 'Kernel', 5.0));

    return [$ada, $linus, $grace];
}

it('lê o mapeamento dos atributos', function (): void {
    $metadata = new MetadataFactory()->for(User::class);

    expect($metadata->table)->toBe('users')
        ->and($metadata->idColumn())->toBe('id')
        ->and($metadata->column('email'))->toBe('email_address')
        ->and($metadata->column('createdAt'))->toBe('created_at')
        ->and($metadata->propertyForColumn('email_address'))->toBe('email')
        ->and(array_keys($metadata->relations))->toBe(['posts']);
});

it('insere a entidade e preenche o ID gerado', function (): void {
    $user = new User('Ada', 'ada@forja.test', role: Role::Admin, settings: ['tema' => 'escuro']);

    $this->em->save($user);

    expect($user->id)->toBe(1)
        ->and($this->db->table('users')->first())->toBe([
            'id' => 1,
            'name' => 'Ada',
            'email_address' => 'ada@forja.test',
            'active' => 1,
            'role' => 'admin',
            'settings' => '{"tema":"escuro"}',
            'created_at' => null,
        ]);
});

it('hidrata tipos a partir do banco', function (): void {
    [$ada] = seedUsers($this->em);

    $found = $this->em->find(User::class, (int) $ada->id);

    expect($found)->toBeInstanceOf(User::class)
        ->and($found?->name)->toBe('Ada')
        ->and($found?->active)->toBeTrue()
        ->and($found?->role)->toBe(Role::Admin)
        ->and($found?->settings)->toBe(['tema' => 'escuro'])
        ->and($found?->createdAt?->format('Y-m-d H:i'))->toBe('2026-01-02 10:00')
        ->and($this->em->find(User::class, 999))->toBeNull();
});

it('atualiza entidades existentes', function (): void {
    [$ada] = seedUsers($this->em);
    $ada->name = 'Ada Lovelace';
    $ada->active = false;

    $this->em->save($ada);

    expect($this->em->find(User::class, (int) $ada->id)?->name)->toBe('Ada Lovelace')
        ->and($this->db->table('users')->count())->toBe(3);
});

it('busca por critérios com ordenação, limite e listas', function (): void {
    seedUsers($this->em);

    $active = $this->em->findBy(User::class, ['active' => true], ['name' => 'desc']);
    $some = $this->em->findBy(User::class, ['name' => ['Ada', 'Grace']], ['name' => 'asc'], limit: 1);

    expect(array_map(fn (User $user): string => $user->name, $active))->toBe(['Linus', 'Ada'])
        ->and(array_map(fn (User $user): string => $user->name, $some))->toBe(['Ada'])
        ->and($this->em->findOneBy(User::class, ['email' => 'grace@forja.test'])?->name)->toBe('Grace')
        ->and($this->em->findAll(User::class))->toHaveCount(3);
});

it('remove entidades', function (): void {
    [, , $grace] = seedUsers($this->em);

    $this->em->delete($grace);

    expect($this->em->findAll(User::class))->toHaveCount(2)
        ->and(fn () => $this->em->delete(new User('Sem', 'id@forja.test')))->toThrow(MappingException::class, 'sem ID');
});

it('consulta entidades com o query builder e pagina', function (): void {
    seedUsers($this->em);

    $posts = $this->em->query(Post::class)->where('rating', '>=', 4)->orderBy('rating', 'desc')->get();
    $page = $this->em->query(User::class)->orderBy('id')->paginate(perPage: 2, page: 1);

    expect(array_map(fn (Post $post): string => $post->title, $posts))->toBe(['Kernel', 'Motores analíticos'])
        ->and($posts[0]->rating)->toBe(5.0)
        ->and($page->items[1])->toBeInstanceOf(User::class)
        ->and($page->total)->toBe(3)
        ->and($this->em->query(User::class)->where('active', false)->count())->toBe(1)
        ->and($this->em->query(User::class)->where('name', 'Ninguém')->first())->toBeNull();
});

it('carrega relações hasMany e belongsTo com eager loading', function (): void {
    seedUsers($this->em);
    $queries = 0;
    $this->db->listen(function () use (&$queries): void {
        $queries++;
    });

    $users = $this->em->query(User::class)->orderBy('id')->with('posts')->get();
    $posts = $this->em->query(Post::class)->orderBy('id')->with('author')->get();

    expect(array_map(fn (Post $post): string => $post->title, $users[0]->posts))->toBe(['Motores analíticos', 'Notas'])
        ->and($users[1]->posts)->toHaveCount(1)
        ->and($users[2]->posts)->toBe([])
        ->and($posts[2]->author?->name)->toBe('Linus')
        ->and($queries)->toBe(4);
});

it('carrega relações sob demanda e valida nomes', function (): void {
    [$ada] = seedUsers($this->em);
    $fresh = $this->em->find(User::class, (int) $ada->id);
    assert($fresh instanceof User);

    $this->em->load($fresh, 'posts');

    expect($fresh->posts)->toHaveCount(2)
        ->and(fn () => $this->em->load($fresh, 'comments'))->toThrow(MappingException::class, 'comments');
});

it('persiste entidades readonly com chave natural', function (): void {
    $this->em->save(new Tag('php', 'PHP'));
    $this->em->save(new Tag('php', 'PHP 8.4'));

    $tag = $this->em->find(Tag::class, 'php');

    expect($tag?->label)->toBe('PHP 8.4')
        ->and($this->db->table('tags')->count())->toBe(1);
});

it('exige o mapeamento da entidade', function (): void {
    $this->em->find(Unmapped::class, 1);
})->throws(MappingException::class, '#[Table]');

it('oferece repositórios tipados', function (): void {
    seedUsers($this->em);
    $repository = new UserRepository($this->em);

    expect(array_map(fn (User $user): string => $user->name, $repository->admins()))->toBe(['Ada'])
        ->and($repository->find(2)?->name)->toBe('Linus')
        ->and($repository->findOneBy(['name' => 'Grace'])?->active)->toBeFalse()
        ->and($repository->query()->count())->toBe(3)
        ->and($repository->findAll())->toHaveCount(3);
});

it('executa operações em transação', function (): void {
    expect(fn () => $this->em->transaction(function (EntityManager $em): never {
        $em->save(new User('Temp', 'temp@forja.test'));

        throw new RuntimeException('cancelar');
    }))->toThrow(RuntimeException::class);

    expect($this->em->findAll(User::class))->toBe([]);
});
