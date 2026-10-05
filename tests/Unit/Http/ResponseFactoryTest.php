<?php

declare(strict_types=1);

use Forja\Http\ResponseFactory;
use Nyholm\Psr7\Response;

beforeEach(function (): void {
    $this->responses = new ResponseFactory();
});

it('cria respostas JSON, HTML, texto e redirecionamentos', function (): void {
    $json = $this->responses->json(['nome' => 'Forjá', 'url' => '/a/b'], 201);
    $html = $this->responses->html('<p>oi</p>');
    $text = $this->responses->text('oi', 202, ['X-A' => '1']);
    $redirect = $this->responses->redirect('/login');

    expect($json->getStatusCode())->toBe(201)
        ->and((string) $json->getBody())->toBe('{"nome":"Forjá","url":"/a/b"}')
        ->and($json->getHeaderLine('Content-Type'))->toBe('application/json; charset=utf-8')
        ->and($html->getHeaderLine('Content-Type'))->toBe('text/html; charset=utf-8')
        ->and($text->getStatusCode())->toBe(202)
        ->and($text->getHeaderLine('X-A'))->toBe('1')
        ->and($redirect->getStatusCode())->toBe(302)
        ->and($redirect->getHeaderLine('Location'))->toBe('/login')
        ->and($this->responses->noContent()->getStatusCode())->toBe(204);
});

it('converte valores de retorno em respostas', function (): void {
    $response = new Response(418);

    expect($this->responses->fromValue($response))->toBe($response)
        ->and($this->responses->fromValue(null)->getStatusCode())->toBe(204)
        ->and((string) $this->responses->fromValue('<b>x</b>')->getBody())->toBe('<b>x</b>')
        ->and((string) $this->responses->fromValue([1, 2])->getBody())->toBe('[1,2]')
        ->and((string) $this->responses->fromValue(true)->getBody())->toBe('true');
});

it('rejeita valores que não viram resposta', function (): void {
    $this->responses->fromValue(new stdClass());
})->throws(InvalidArgumentException::class, 'stdClass');
