# Validação e DTOs

Declare as regras como atributos nas propriedades de um DTO:

```php
final readonly class CreateUserData
{
    public function __construct(
        #[Required, Min(3), Max(50)] public string $name,
        #[Required, Email] public string $email,
        #[Min(18)] public ?int $age = null,
        public Role $role = Role::Member,
        #[Url] public ?string $website = null,
        public ?AddressData $address = null,          // DTO aninhado
        #[In(['pt', 'en'])] public string $locale = 'pt',
    ) {}
}
```

| Regra | Valida |
| --- | --- |
| `Required` | presente e não vazio |
| `Email` | e-mail válido |
| `Min(n)` / `Max(n)` | caracteres (texto), valor (número) ou itens (lista) |
| `Regex('/.../')` | formato |
| `In([...])` | valor permitido |
| `Url` | URL http(s) |

Todas aceitam uma mensagem própria: `#[Required('Informe o nome.')]`. Regras
ignoram `null`, exceto `Required`. Crie regras novas implementando
`Forja\Validation\Rule\RuleInterface` como atributo.

## No controller

```php
#[Route('/users', methods: ['POST'])]
public function store(#[FromBody] CreateUserData $data): ResponseInterface { ... }

#[Route('/users')]
public function index(#[FromQuery] UserFilter $filter): ResourceCollection { ... }
```

`#[FromBody]` lê formulários, `application/x-www-form-urlencoded` e JSON;
`#[FromQuery]` lê a query string. Os valores são convertidos para o tipo
declarado (escalares, enums, `DateTimeImmutable`, arrays e DTOs aninhados) e
validados antes de o DTO ser criado. Qualquer erro gera
`ValidationException` (422):

```json
{
  "error": {
    "status": 422,
    "message": "Os dados enviados são inválidos.",
    "errors": {
      "name": ["O campo name deve ter pelo menos 3 caracteres."],
      "email": ["O campo email é obrigatório."],
      "address.zip": ["O campo zip está em um formato inválido."]
    }
  }
}
```

JSON malformado gera 400.

## Fora do controller

```php
$data = $container->get(DtoMapper::class)->map(CreateUserData::class, $array);

$errors = new Validator()->validate($objeto);   // array<campo, list<mensagem>>
new Validator()->assertValid($objeto);          // lança ValidationException
```
