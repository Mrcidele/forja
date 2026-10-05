<?php

declare(strict_types=1);

namespace Forja\Log\Handler;

use Forja\Log\Level;
use Forja\Log\LogRecord;

interface HandlerInterface
{
    public function handles(Level $level): bool;

    public function handle(LogRecord $record): void;
}
