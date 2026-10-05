<?php

declare(strict_types=1);

use Forja\View\Compiler;
use Forja\View\Engine;
use Forja\View\ViewNotFoundException;

beforeEach(function (): void {
    $this->cache = sys_get_temp_dir() . '/forja-views-' . bin2hex(random_bytes(4));
    $this->engine = new Engine(dirname(__DIR__, 2) . '/Fixtures/Views', $this->cache);
});

afterEach(function (): void {
    removeDirectory($this->cache);
});

function normalizeHtml(string $html): string
{
    return trim((string) preg_replace('/\s+/', ' ', $html));
}

it('renderiza layouts, seções, includes, condicionais e laços com escape', function (): void {
    $html = $this->engine->render('home', ['user' => '<Ada>', 'items' => ['um', 'dois & três']]);

    expect(normalizeHtml($html))->toBe(
        '<!doctype html> <title>Página &lt;inicial&gt;</title> <nav><a class="ativo">Início</a> &lt;Ada&gt; </nav> '
        . '<main> <h1>Olá, &lt;Ada&gt;!</h1> <ul> <li>um</li> <li>dois &amp; três</li> </ul> </main>',
    );
});

it('usa o bloco else e valores padrão do yield', function (): void {
    $html = $this->engine->render('home', ['user' => 'Linus', 'items' => []]);

    expect($html)->toContain('<p>Nada aqui.</p>')->not->toContain('<ul>');
});

it('compila as demais diretivas', function (): void {
    $html = $this->engine->render('directives', ['admin' => false, 'html' => '<b>cru</b>', 'csrf_token' => 'abc', 'texto' => 'a)b']);

    expect(normalizeHtml($html))->toBe(
        'visitante 1234 8 <b>cru</b> {{ literal }} contato@forja.test <form><input type="hidden" name="_token" value="abc"></form> tem parêntese',
    );
});

it('compartilha valores com todas as views', function (): void {
    $this->engine->share('user', 'Compartilhado');

    expect($this->engine->render('partials.nav', ['active' => 'home']))->toContain('Compartilhado');
});

it('guarda o template compilado em cache e recompila quando a fonte muda', function (): void {
    $views = sys_get_temp_dir() . '/forja-views-src-' . bin2hex(random_bytes(4));
    mkdir($views);
    file_put_contents($views . '/page.forja.php', 'v1 {{ $x }}');
    $engine = new Engine($views, $this->cache);

    expect($engine->render('page', ['x' => 1]))->toBe('v1 1')
        ->and($engine->compiledPath('page'))->toBeFile();

    file_put_contents($views . '/page.forja.php', 'v2 {{ $x }}');
    touch($views . '/page.forja.php', time() + 10);

    expect($engine->render('page', ['x' => 2]))->toBe('v2 2');

    removeDirectory($views);
});

it('falha com views inexistentes, seções abertas e valores não exibíveis', function (): void {
    expect(fn () => $this->engine->render('nada'))->toThrow(ViewNotFoundException::class, 'View [nada] não encontrada')
        ->and(fn () => $this->engine->render('unclosed'))->toThrow(RuntimeException::class, '@endsection')
        ->and(fn () => $this->engine->render('partials.nav', ['active' => 'x', 'user' => ['array']]))->toThrow(LogicException::class, 'array')
        ->and($this->engine->exists('home'))->toBeTrue()
        ->and($this->engine->exists('nada'))->toBeFalse();
});

it('rejeita diretivas com parênteses não fechados', function (): void {
    new Compiler()->compile('@if($a');
})->throws(RuntimeException::class, 'Parêntese não fechado');
