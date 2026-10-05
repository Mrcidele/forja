<?php

declare(strict_types=1);

use Forja\Tests\Fixtures\Validation\SearchQuery;
use Forja\Validation\Rule\Email;
use Forja\Validation\Rule\In;
use Forja\Validation\Rule\Max;
use Forja\Validation\Rule\Min;
use Forja\Validation\Rule\Regex;
use Forja\Validation\Rule\Required;
use Forja\Validation\Rule\Url;
use Forja\Validation\ValidationException;
use Forja\Validation\Validator;

it('valida objetos já construídos', function (): void {
    $query = new SearchQuery();
    $query->page = 0;

    expect(new Validator()->validate($query))->toBe(['page' => ['O campo page deve ser no mínimo 1.']])
        ->and(fn () => new Validator()->assertValid($query))->toThrow(ValidationException::class);

    $query->page = 2;

    expect(new Validator()->validate($query))->toBe([]);
});

it('aplica cada regra', function (object $rule, mixed $valid, mixed $invalid): void {
    expect($rule->validate('campo', $valid))->toBeNull()
        ->and($rule->validate('campo', $invalid))->toBeString()->toContain('campo');
})->with([
    'required' => [new Required(), 'x', '  '],
    'email' => [new Email(), 'a@b.com', 'a@'],
    'min texto' => [new Min(2), 'ab', 'a'],
    'min lista' => [new Min(1), ['a'], []],
    'max número' => [new Max(10), 10, 10.5],
    'regex' => [new Regex('/^\d+$/'), '123', '12a'],
    'in' => [new In(['a', 'b']), 'a', 'c'],
    'url' => [new Url(), 'http://forja.dev', 'javascript:alert(1)'],
]);

it('ignora valores nulos, exceto em Required, e aceita mensagens próprias', function (): void {
    expect(new Email()->validate('email', null))->toBeNull()
        ->and(new Required('Preencha o nome.')->validate('nome', null))->toBe('Preencha o nome.');
});
