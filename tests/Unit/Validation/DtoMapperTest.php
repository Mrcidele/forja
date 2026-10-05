<?php

declare(strict_types=1);

use Forja\Tests\Fixtures\Database\Role;
use Forja\Tests\Fixtures\Validation\AddressData;
use Forja\Tests\Fixtures\Validation\CreateUserData;
use Forja\Tests\Fixtures\Validation\SearchQuery;
use Forja\Validation\DtoMapper;
use Forja\Validation\ValidationException;

function validationErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->getErrors();
    }

    throw new LogicException('Era esperada uma ValidationException.');
}

beforeEach(function (): void {
    $this->mapper = new DtoMapper();
});

it('converte os valores para os tipos declarados e instancia o DTO', function (): void {
    $dto = $this->mapper->map(CreateUserData::class, [
        'name' => 'Ada',
        'email' => 'ada@forja.test',
        'age' => '36',
        'role' => 'admin',
        'website' => 'https://forja.dev',
        'address' => ['city' => 'Campinas', 'zip' => '13000-000'],
        'newsletter' => 'on',
        'birthday' => '1815-12-10',
        'locale' => 'en',
        'tags' => ['php'],
        'ignorado' => 'x',
    ]);

    expect($dto->age)->toBe(36)
        ->and($dto->role)->toBe(Role::Admin)
        ->and($dto->address)->toEqual(new AddressData('Campinas', '13000-000'))
        ->and($dto->newsletter)->toBeTrue()
        ->and($dto->birthday?->format('Y-m-d'))->toBe('1815-12-10')
        ->and($dto->tags)->toBe(['php']);
});

it('usa os valores padrão dos campos ausentes', function (): void {
    $dto = $this->mapper->map(CreateUserData::class, ['name' => 'Linus', 'email' => 'linus@forja.test']);

    expect($dto->age)->toBeNull()
        ->and($dto->role)->toBe(Role::Member)
        ->and($dto->locale)->toBe('pt');
});

it('reúne erros de presença, tipo e regras por campo', function (): void {
    $errors = validationErrors(fn () => $this->mapper->map(CreateUserData::class, [
        'name' => 'Al',
        'age' => 'dezoito',
        'role' => 'root',
        'website' => 'ftp://forja.dev',
        'address' => ['zip' => '123'],
        'newsletter' => 'talvez',
        'birthday' => 'ontem à tarde',
        'locale' => 'fr',
        'tags' => ['a', 'b', 'c', 'd'],
    ]));

    expect($errors)->toBe([
        'name' => ['O campo name deve ter pelo menos 3 caracteres.'],
        'email' => ['O campo email é obrigatório.'],
        'age' => ['O campo age deve ser um número inteiro.'],
        'role' => ['O campo role deve ser um destes valores: admin, member.'],
        'website' => ['O campo website deve ser uma URL válida.'],
        'address.city' => ['O campo city é obrigatório.'],
        'address.zip' => ['O campo zip está em um formato inválido.'],
        'newsletter' => ['O campo newsletter deve ser verdadeiro ou falso.'],
        'birthday' => ['O campo birthday deve ser uma data válida.'],
        'locale' => ['O campo locale deve ser um destes valores: pt, en.'],
        'tags' => ['O campo tags deve ter no máximo 3 itens.'],
    ]);
});

it('valida regras numéricas e de e-mail', function (): void {
    $errors = validationErrors(fn () => $this->mapper->map(CreateUserData::class, ['name' => str_repeat('a', 51), 'email' => 'invalido', 'age' => 17]));

    expect($errors)->toBe([
        'name' => ['O campo name deve ter no máximo 50 caracteres.'],
        'email' => ['O campo email deve ser um e-mail válido.'],
        'age' => ['O campo age deve ser no mínimo 18.'],
    ]);
});

it('preenche DTOs baseados em propriedades públicas', function (): void {
    $query = $this->mapper->map(SearchQuery::class, ['page' => '3', 'term' => 'php']);

    expect($query->page)->toBe(3)
        ->and($query->perPage)->toBe(15)
        ->and($query->term)->toBe('php')
        ->and(validationErrors(fn () => $this->mapper->map(SearchQuery::class, ['page' => '0', 'perPage' => '500'])))
        ->toBe(['page' => ['O campo page deve ser no mínimo 1.'], 'perPage' => ['O campo perPage deve ser no máximo 100.']]);
});

it('gera exceção 422 com os erros', function (): void {
    try {
        $this->mapper->map(AddressData::class, []);
    } catch (ValidationException $exception) {
        expect($exception->getStatusCode())->toBe(422)
            ->and($exception->getErrors())->toHaveKeys(['city', 'zip']);
    }
});
