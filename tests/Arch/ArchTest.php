<?php

declare(strict_types=1);

arch('todo arquivo declara strict_types')
    ->expect('Forja')
    ->toUseStrictTypes();

arch('sem funções de debug esquecidas')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray', 'die'])
    ->not->toBeUsed();

arch('sem funções inseguras')
    ->expect(['md5', 'sha1', 'rand', 'mt_rand', 'uniqid', 'eval', 'exec', 'shell_exec', 'system', 'passthru'])
    ->not->toBeUsed();
