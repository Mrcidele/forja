<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Container;

final class WithDefaults
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public Logger $logger,
        public Mode $mode = Mode::Safe,
        public array $tags = ['a', 'b'],
        public ?ClockInterface $clock = null,
    ) {
    }
}
