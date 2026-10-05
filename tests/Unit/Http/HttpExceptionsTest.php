<?php

declare(strict_types=1);

use Forja\Http\Exception\BadRequestHttpException;
use Forja\Http\Exception\ConflictHttpException;
use Forja\Http\Exception\ForbiddenHttpException;
use Forja\Http\Exception\GoneHttpException;
use Forja\Http\Exception\HttpException;
use Forja\Http\Exception\NotFoundHttpException;
use Forja\Http\Exception\ServiceUnavailableHttpException;
use Forja\Http\Exception\UnauthorizedHttpException;
use Forja\Http\Exception\UnprocessableEntityHttpException;

it('associa cada exceção ao status HTTP correto', function (HttpException $exception, int $status): void {
    expect($exception->getStatusCode())->toBe($status)
        ->and($exception->getMessage())->not->toBe('');
})->with([
    [new BadRequestHttpException(), 400],
    [new UnauthorizedHttpException(), 401],
    [new ForbiddenHttpException(), 403],
    [new NotFoundHttpException(), 404],
    [new ConflictHttpException(), 409],
    [new GoneHttpException(), 410],
    [new UnprocessableEntityHttpException(), 422],
    [new ServiceUnavailableHttpException(), 503],
]);

it('inclui headers específicos', function (): void {
    expect(new UnauthorizedHttpException('Bearer realm="api"')->getHeaders())->toBe(['WWW-Authenticate' => 'Bearer realm="api"'])
        ->and(new ServiceUnavailableHttpException(120)->getHeaders())->toBe(['Retry-After' => '120'])
        ->and(new UnprocessableEntityHttpException(['nome' => ['obrigatório']])->getErrors())->toBe(['nome' => ['obrigatório']]);
});
