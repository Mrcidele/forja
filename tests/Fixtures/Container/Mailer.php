<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Container;

final class Mailer
{
    public function __construct(public Logger $logger, public string $from = 'noreply@forja.test')
    {
    }
}
