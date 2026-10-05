# Banco de dados

## Conexão

Configure em `config/database.php` (SQLite, MySQL ou PostgreSQL). A
`Forja\Database\Connection` conecta na primeira consulta e usa sempre
prepared statements:

```php
$db->select('select * from users where active = ?', [true]);
$db->selectOne('select * from users where id = ?', [1]);
$db->scalar('select count(*) from users');
$db->insert('insert into users (name) values (?)', ['Ada']);
$db->update('update users set active = ? where id = ?', [false, 1]); // linhas afetadas
$db->lastInsertId();
```

Valores `bool`, `null`, `DateTimeInterface` e enums são convertidos
automaticamente. Falhas lançam `QueryException` com o SQL e os valores.

```php
$db->transaction(function (Connection $db): void {
    $db->table('accounts')->where('id', 1)->increment('balance', -100);
    $db->transaction(fn () => ...); // aninhada com SAVEPOINT
});
```

## Query builder

```php
$users = $db->table('users')
    ->select('users.id', 'users.name', $db->raw('count(posts.id) as total'))
    ->leftJoin('posts', 'posts.user_id', '=', 'users.id')
    ->where('active', true)
    ->where('age', '>=', 18)
    ->where(fn ($q) => $q->where('role', 'admin')->orWhere('role', 'owner'))
    ->whereIn('country', ['BR', 'PT'])
    ->whereNotNull('email')
    ->groupBy('users.id', 'users.name')
    ->having('total', '>', 0)
    ->orderByDesc('total')
    ->limit(10)
    ->get();                         // list<array<string, mixed>>
```

Também: `first()`, `find($id)`, `value('coluna')`, `pluck('nome', 'id')`,
`count()`, `sum()`, `avg()`, `min()`, `max()`, `exists()`, `whereBetween()`,
`whereColumn()`, `whereRaw()`, `distinct()`, `offset()`, `forPage()`,
`toSql()` e `getBindings()`.

```php
$page = $db->table('posts')->orderByDesc('id')->paginate(perPage: 15, page: 2);
$page->items; $page->total; $page->lastPage(); json_encode($page); // {"data": [...], "meta": {...}}

$db->table('users')->insert(['name' => 'Ada', 'email' => 'ada@site.com']);
$db->table('users')->insert([[...], [...]]);         // várias linhas
$id = $db->table('users')->insertGetId([...]);
$db->table('users')->where('id', $id)->update(['name' => 'Ada L.']);
$db->table('users')->where('active', false)->delete();
```

Operadores ficam numa lista permitida e identificadores são escapados pela
gramática de cada banco. Use `$db->raw('...')` para trechos de SQL cru.

## Migrations

```bash
php forja make:migration create_posts_table
php forja migrate
php forja migrate:status
php forja migrate:rollback --step=1
php forja migrate:reset
```

```php
// database/migrations/2026_10_05_120000_create_posts_table.php
return new class () extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('body')->nullable();
            $table->boolean('published')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index('title');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('posts');
    }
};
```

O nome do arquivo define a ordem. A tabela `migrations` registra cada
migration e o lote; `rollback` desfaz lotes inteiros. Cada migration roda
numa transação (`public bool $withinTransaction = false;` desativa).

Tipos: `id`, `string`, `text`, `integer`, `bigInteger`, `boolean`,
`decimal`, `float`, `date`, `dateTime`, `timestamp`, `json`, `uuid`,
`foreignId`. Modificadores: `nullable()`, `default()`, `unique()`,
`unsigned()`, `primary()`, `constrained()`, `onDelete()`, `onUpdate()`.
Alterações: `$schema->table('posts', fn ($t) => $t->string('slug')->nullable())`,
`dropColumn()`, `rename()`, `dropIfExists()` e `hasTable()`.

## ORM (Data Mapper)

Entidades são classes comuns mapeadas por atributos; o `EntityManager` busca
e persiste.

```php
#[Table('users')]
final class User
{
    #[Id]
    public ?int $id = null;

    /** @var list<Post> */
    #[HasMany(Post::class, foreignKey: 'user_id')]
    public array $posts = [];

    public function __construct(
        #[Column] public string $name,
        #[Column(name: 'email_address')] public string $email,
        #[Column] public Role $role = Role::Member,        // enum
        #[Column] public array $settings = [],             // JSON
        #[Column] public ?DateTimeImmutable $createdAt = null,
    ) {}
}

#[Table('posts')]
final class Post
{
    #[Id] public ?int $id = null;

    #[BelongsTo(User::class, foreignKey: 'user_id')]
    public ?User $author = null;

    public function __construct(#[Column] public int $userId, #[Column] public string $title) {}
}
```

Colunas sem nome explícito usam o nome da propriedade em snake_case.

```php
$em->save($user);                     // insert (preenche o ID) ou update
$em->find(User::class, 1);
$em->findBy(User::class, ['role' => Role::Admin], ['name' => 'asc'], limit: 10);
$em->findOneBy(User::class, ['email' => 'ada@site.com']);
$em->delete($user);

$em->query(Post::class)->where('title', 'like', '%php%')->with('author')->paginate(10, 1);
$em->load($user, 'posts');            // carrega relação sob demanda
```

Relações são carregadas com uma consulta por relação (sem N+1). Entidades
readonly e chaves naturais (`#[Id(generated: false)]`) são suportadas.

Repositórios tipados:

```php
/** @extends Repository<User> */
final class UserRepository extends Repository
{
    protected function entity(): string { return User::class; }

    /** @return list<User> */
    public function admins(): array { return $this->findBy(['role' => Role::Admin]); }
}
```
