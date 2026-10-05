<?php

declare(strict_types=1);

namespace Forja\Session;

interface SessionStoreInterface
{
    /**
     * @return array<string, mixed>|null null quando a sessão não existe
     */
    public function read(string $id): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function write(string $id, array $data, int $lifetime): void;

    public function destroy(string $id): void;
}
