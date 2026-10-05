<?php

declare(strict_types=1);

namespace Forja\Events;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Base para eventos cujos ouvintes podem interromper a propagação.
 */
abstract class StoppableEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
