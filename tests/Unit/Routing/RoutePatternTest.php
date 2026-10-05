<?php

declare(strict_types=1);

use Forja\Routing\Exception\InvalidRouteException;
use Forja\Routing\RoutePattern;

it('separa partes literais e placeholders', function (): void {
    expect(RoutePattern::parse('/users/{id:int}/posts/{slug}'))->toBe([
        '/users/',
        ['name' => 'id', 'regex' => '\d+', 'type' => 'int'],
        '/posts/',
        ['name' => 'slug', 'regex' => '[^/]+', 'type' => null],
    ]);
});

it('aceita regex própria, inclusive com quantificadores entre chaves', function (): void {
    expect(RoutePattern::parse('/lang/{code:[a-z]{2}}'))->toBe([
        '/lang/',
        ['name' => 'code', 'regex' => '[a-z]{2}', 'type' => null],
    ]);
});

it('rejeita rotas inválidas', function (string $path, string $message): void {
    expect(fn (): array => RoutePattern::parse($path))->toThrow(InvalidRouteException::class, $message);
})->with([
    'parâmetro repetido' => ['/a/{id}/b/{id}', 'mais de uma vez'],
    'grupo capturante' => ['/a/{x:(foo|bar)}', 'não capturantes'],
    'regex inválida' => ['/a/{x:[a-z}', 'Regex inválida'],
    'chave solta' => ['/a/{x', 'Placeholder malformado'],
    'regex que não compila' => ['/a/{x:a**}', 'Regex inválida'],
]);
