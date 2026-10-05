# Linha de comando

```bash
php forja list
```

| Comando | Descrição |
| --- | --- |
| `migrate` | executa as migrations pendentes |
| `migrate:rollback` (`rollback`) `--step=N` | desfaz os últimos lotes |
| `migrate:status` | situação de cada migration |
| `migrate:reset` | desfaz todas |
| `make:controller Nome [--invokable]` | controller com rotas por atributos |
| `make:middleware Nome` | middleware PSR-15 |
| `make:migration create_posts_table` | migration versionada |
| `make:entity Nome` | entidade do ORM |
| `route:list` | rotas registradas |
| `route:cache [--clear]` | cache do mapa de rotas |
| `config:cache [--clear]` | cache da configuração |
| `cache:clear` | limpa o cache da aplicação e os arquivos compilados |
| `optimize` | caches de configuração, rotas, container e preload do OPcache |

Os geradores aceitam subpastas (`make:controller Admin/Report`) e `--force`
para sobrescrever. Para personalizar um stub, copie-o de
`vendor/forja/framework/stubs` para `stubs/` na raiz da aplicação; os
marcadores são `{{ namespace }}`, `{{ class }}`, `{{ route }}`, `{{ name }}`
e `{{ table }}`.

## Comandos próprios

Use symfony/console e registre a classe em `config/console.php`. O comando é
resolvido pelo container, então dependências chegam pelo construtor:

```php
#[AsCommand(name: 'users:prune', description: 'Remove usuários inativos')]
final class PruneUsersCommand extends Command
{
    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $removed = $this->db->table('users')->where('active', false)->delete();
        $output->writeln("{$removed} usuário(s) removido(s).");

        return self::SUCCESS;
    }
}
```
