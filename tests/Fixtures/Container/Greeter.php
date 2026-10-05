<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Container;

final class Greeter
{
    public function greet(Logger $logger, string $name = 'mundo'): string
    {
        return $logger->clock->now() . ': olá, ' . $name;
    }

    public static function shout(string $text): string
    {
        return strtoupper($text);
    }

    public function __invoke(ClockInterface $clock): string
    {
        return 'invocado em ' . $clock->now();
    }
}
