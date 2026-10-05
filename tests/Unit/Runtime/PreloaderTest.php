<?php

declare(strict_types=1);

use Forja\Runtime\Preloader;

it('gera um script de preload que carrega as classes pelo autoloader', function (): void {
    $file = sys_get_temp_dir() . '/forja-preload-' . bin2hex(random_bytes(4)) . '/preload.php';
    $src = dirname(__DIR__, 3) . '/src';

    new Preloader()->dump([$src], dirname(__DIR__, 3) . '/vendor/autoload.php', $file);
    $code = (string) file_get_contents($file);

    expect($code)
        ->toContain("require_once '" . dirname(__DIR__, 3) . "/vendor/autoload.php';")
        ->toContain("'Forja\\\\Container\\\\Container',")
        ->toContain("'Forja\\\\Routing\\\\Router',")
        ->toContain('opcache.preload=')
        ->and(require $file)->toBeGreaterThan(50);

    unlink($file);
    rmdir(dirname($file));
});
