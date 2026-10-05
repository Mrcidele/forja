<?php

declare(strict_types=1);

use Forja\Database\Query\Paginator;
use Forja\Tests\Fixtures\Database\Role;
use Forja\Tests\Fixtures\Database\User;
use Forja\Tests\Fixtures\Validation\UserResource;

function user(int $id, string $name, Role $role = Role::Member): User
{
    $user = new User($name, strtolower($name) . '@forja.test', role: $role);
    $user->id = $id;

    return $user;
}

it('transforma um recurso com envelope "data" e dados extras', function (): void {
    expect(json_encode(UserResource::make(user(1, 'Ada', Role::Admin))))
        ->toBe('{"data":{"id":1,"nome":"Ada","papel":"admin"},"versao":"1"}');
});

it('transforma coleções e páginas', function (): void {
    $collection = UserResource::collection([user(1, 'Ada'), user(2, 'Linus')]);
    $page = UserResource::collection(new Paginator([user(3, 'Grace')], total: 3, perPage: 1, currentPage: 3));

    expect(json_encode($collection))->toBe('{"data":[{"id":1,"nome":"Ada","papel":"member"},{"id":2,"nome":"Linus","papel":"member"}]}')
        ->and($page->jsonSerialize()['meta'])->toBe(['total' => 3, 'per_page' => 1, 'current_page' => 3, 'last_page' => 3]);
});

it('gera respostas JSON com status', function (): void {
    $response = UserResource::make(user(1, 'Ada'))->response(201);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getHeaderLine('Content-Type'))->toBe('application/json; charset=utf-8')
        ->and(UserResource::collection([])->response()->getStatusCode())->toBe(200);
});
