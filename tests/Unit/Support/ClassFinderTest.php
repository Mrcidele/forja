<?php

declare(strict_types=1);

use Forja\Support\ClassFinder;

it('encontra classes nomeadas e ignora ::class e classes anônimas', function (): void {
    $code = <<<'PHP'
        <?php
        namespace App\Http;

        use Other\Thing;

        final class First
        {
            public function make(): object
            {
                $name = Thing::class;

                return new class {};
            }
        }

        abstract class Second {}
        interface NotAClass {}
        PHP;

    expect(new ClassFinder()->classesIn($code))->toBe(['App\Http\First', 'App\Http\Second']);
});

it('suporta arquivos sem namespace', function (): void {
    expect(new ClassFinder()->classesIn('<?php class Global_ {}'))->toBe(['Global_']);
});
