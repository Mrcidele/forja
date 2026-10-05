<?php

declare(strict_types=1);

use Forja\Config\ConfigLoader;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir() . '/forja-config-' . bin2hex(random_bytes(4));
    mkdir($this->directory);
});

afterEach(function (): void {
    removeDirectory($this->directory);
});

it('carrega cada arquivo como uma chave', function (): void {
    file_put_contents($this->directory . '/app.php', "<?php return ['name' => 'Forja'];");
    file_put_contents($this->directory . '/database.php', "<?php return ['default' => 'sqlite'];");

    expect(new ConfigLoader()->load($this->directory))->toBe([
        'app' => ['name' => 'Forja'],
        'database' => ['default' => 'sqlite'],
    ]);
});

it('exige que os arquivos retornem arrays', function (): void {
    file_put_contents($this->directory . '/ruim.php', '<?php return 42;');

    new ConfigLoader()->load($this->directory);
})->throws(RuntimeException::class, 'deve retornar um array');

it('grava e lê o cache de configuração', function (): void {
    $loader = new ConfigLoader();
    $loader->dump(['app' => ['name' => 'Forja', 'debug' => false]], $this->directory . '/cache/config.php');

    expect($loader->loadCached($this->directory . '/cache/config.php'))->toBe(['app' => ['name' => 'Forja', 'debug' => false]]);
});
