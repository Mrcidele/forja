<?php

declare(strict_types=1);

namespace Forja\Events;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Guarda os ouvintes por tipo de evento (classe, classe-mãe ou interface).
 *
 * Ouvintes podem ser callables ou nomes de classe, resolvidos pelo
 * container apenas quando o evento acontece e chamados via __invoke()
 * ou handle(). Maior prioridade executa antes; empates seguem a ordem de registro.
 */
final class ListenerProvider implements ListenerProviderInterface
{
    /** @var array<string, list<array{listener: callable|string, priority: int, order: int}>> */
    private array $listeners = [];

    private int $order = 0;

    public function __construct(
        private readonly ?ContainerInterface $container = null,
    ) {
    }

    /**
     * @param class-string $event
     * @param callable|class-string $listener
     */
    public function listen(string $event, callable|string $listener, int $priority = 0): void
    {
        $this->listeners[$event][] = ['listener' => $listener, 'priority' => $priority, 'order' => $this->order++];
    }

    /**
     * @param class-string $event
     */
    public function hasListeners(string $event): bool
    {
        return ($this->listeners[$event] ?? []) !== [];
    }

    /**
     * @return iterable<callable(object): mixed>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $matches = [];

        foreach ($this->listeners as $type => $listeners) {
            if ($event instanceof $type) {
                $matches = [...$matches, ...$listeners];
            }
        }

        usort($matches, static fn (array $a, array $b): int => [$b['priority'], $a['order']] <=> [$a['priority'], $b['order']]);

        foreach ($matches as $match) {
            yield $this->resolve($match['listener']);
        }
    }

    /**
     * @return callable(object): mixed
     */
    private function resolve(callable|string $listener): callable
    {
        if (is_callable($listener)) {
            return $listener(...);
        }

        $instance = $this->container?->get($listener);

        if (is_object($instance) && method_exists($instance, '__invoke')) {
            return $instance->__invoke(...);
        }

        if (is_object($instance) && method_exists($instance, 'handle')) {
            return $instance->handle(...);
        }

        throw new InvalidArgumentException(sprintf('O ouvinte [%s] precisa ser invocável ou ter um método handle().', $listener));
    }
}
