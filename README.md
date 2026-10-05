# Forja

Framework PHP construído do zero sobre as PSRs: os componentes centrais
(container, router, pipeline de middleware) são implementações próprias, mas
seguem as interfaces padronizadas para serem interoperáveis com o ecossistema.

## Requisitos

- PHP 8.4+
- Composer 2

## Desenvolvimento

```bash
composer install

composer test            # Pest
composer analyse         # PHPStan nível max
composer lint            # Pint (PSR-12) em modo verificação
composer format          # Pint aplicando correções
composer refactor:check  # Rector em dry-run
composer check           # tudo acima, na ordem da CI
```
