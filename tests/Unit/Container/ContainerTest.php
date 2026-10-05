<?php

declare(strict_types=1);

use Forja\Container\Container;
use Forja\Container\Exception\CircularDependencyException;
use Forja\Container\Exception\ContainerException;
use Forja\Container\Exception\NotFoundException;
use Forja\Tests\Fixtures\Container\AbstractService;
use Forja\Tests\Fixtures\Container\CircularA;
use Forja\Tests\Fixtures\Container\ClockInterface;
use Forja\Tests\Fixtures\Container\FixedClock;
use Forja\Tests\Fixtures\Container\Greeter;
use Forja\Tests\Fixtures\Container\Logger;
use Forja\Tests\Fixtures\Container\Mailer;
use Forja\Tests\Fixtures\Container\NeedsScalar;
use Forja\Tests\Fixtures\Container\NoConstructor;
use Forja\Tests\Fixtures\Container\NullableDependency;
use Forja\Tests\Fixtures\Container\OptionalDependency;
use Forja\Tests\Fixtures\Container\Variadic;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

describe('PSR-11', function (): void {
    it('implementa ContainerInterface e resolve a si mesmo', function (): void {
        $container = new Container();

        expect($container)->toBeInstanceOf(ContainerInterface::class)
            ->and($container->get(ContainerInterface::class))->toBe($container)
            ->and($container->get(Container::class))->toBe($container);
    });

    it('informa se consegue resolver uma entrada', function (): void {
        $container = new Container();
        $container->instance('config', ['debug' => true]);

        expect($container->has('config'))->toBeTrue()
            ->and($container->has(NoConstructor::class))->toBeTrue()
            ->and($container->has(ClockInterface::class))->toBeFalse()
            ->and($container->has(AbstractService::class))->toBeFalse()
            ->and($container->has('inexistente'))->toBeFalse();
    });

    it('lança NotFoundExceptionInterface para entradas desconhecidas', function (string $id): void {
        expect(fn (): mixed => new Container()->get($id))
            ->toThrow(NotFoundException::class);
    })->with(['inexistente', ClockInterface::class, AbstractService::class]);

    it('usa exceções das interfaces PSR', function (): void {
        expect(new NotFoundException())->toBeInstanceOf(NotFoundExceptionInterface::class)
            ->and(new ContainerException())->toBeInstanceOf(ContainerExceptionInterface::class);
    });
});

describe('autowiring', function (): void {
    it('instancia classes sem construtor', function (): void {
        expect(new Container()->get(NoConstructor::class))->toBeInstanceOf(NoConstructor::class);
    });

    it('resolve dependências recursivamente', function (): void {
        $container = new Container();
        $container->bind(ClockInterface::class, FixedClock::class);

        $mailer = $container->get(Mailer::class);

        expect($mailer->logger->clock)->toBeInstanceOf(FixedClock::class)
            ->and($mailer->from)->toBe('noreply@forja.test');
    });

    it('cria uma nova instância a cada get() para classes não registradas', function (): void {
        $container = new Container();

        expect($container->get(NoConstructor::class))->not->toBe($container->get(NoConstructor::class));
    });

    it('usa valor padrão ou null quando a dependência não pode ser resolvida', function (): void {
        $container = new Container();

        $optional = $container->get(OptionalDependency::class);
        $nullable = $container->get(NullableDependency::class);

        expect($optional->clock)->toBeNull()
            ->and($optional->retries)->toBe(3)
            ->and($nullable->clock)->toBeNull();
    });

    it('prefere o binding ao valor padrão', function (): void {
        $container = new Container();
        $container->bind(ClockInterface::class, FixedClock::class);

        expect($container->get(OptionalDependency::class)->clock)->toBeInstanceOf(FixedClock::class);
    });

    it('falha com mensagem clara para parâmetros escalares sem valor', function (): void {
        new Container()->get(NeedsScalar::class);
    })->throws(ContainerException::class, 'parâmetro $dsn de ' . NeedsScalar::class . '::__construct()');

    it('detecta dependências circulares', function (): void {
        new Container()->get(CircularA::class);
    })->throws(
        CircularDependencyException::class,
        'Forja\Tests\Fixtures\Container\CircularA -> Forja\Tests\Fixtures\Container\CircularB -> Forja\Tests\Fixtures\Container\CircularA',
    );

    it('detecta dependências circulares entre fábricas', function (): void {
        $container = new Container();
        $container->factory('a', static fn (Container $c): mixed => $c->get('b'));
        $container->factory('b', static fn (Container $c): mixed => $c->get('a'));

        $container->get('a');
    })->throws(CircularDependencyException::class, 'a -> b -> a');

    it('continua funcionando depois de uma falha de resolução', function (): void {
        $container = new Container();

        try {
            $container->get(CircularA::class);
        } catch (CircularDependencyException) {
        }

        expect($container->get(NoConstructor::class))->toBeInstanceOf(NoConstructor::class);
    });

    it('resolve parâmetros variádicos como lista vazia', function (): void {
        expect(new Container()->get(Variadic::class)->clocks)->toBe([]);
    });
});

describe('bindings', function (): void {
    it('liga interface a implementação', function (): void {
        $container = new Container();
        $container->bind(ClockInterface::class, FixedClock::class);

        expect($container->get(ClockInterface::class))->toBeInstanceOf(FixedClock::class)
            ->and($container->get(ClockInterface::class))->not->toBe($container->get(ClockInterface::class));
    });

    it('compartilha singletons', function (): void {
        $container = new Container();
        $container->singleton(ClockInterface::class, FixedClock::class);
        $container->singleton(Logger::class);

        expect($container->get(ClockInterface::class))->toBe($container->get(ClockInterface::class))
            ->and($container->get(Logger::class))->toBe($container->get(Logger::class))
            ->and($container->get(Logger::class)->clock)->toBe($container->get(ClockInterface::class));
    });

    it('executa fábricas a cada resolução, injetando dependências', function (): void {
        $container = new Container();
        $container->instance('time', '12:00');
        $container->factory(ClockInterface::class, static fn (Container $c): FixedClock => new FixedClock((string) $c->get('time')));

        $first = $container->get(ClockInterface::class);

        expect($first->now())->toBe('12:00')
            ->and($first)->not->toBe($container->get(ClockInterface::class));
    });

    it('injeta dependências por tipo nas closures de fábrica', function (): void {
        $container = new Container();
        $container->bind(ClockInterface::class, FixedClock::class);
        $container->singleton(Mailer::class, static fn (Logger $logger): Mailer => new Mailer($logger, 'equipe@forja.test'));

        expect($container->get(Mailer::class)->from)->toBe('equipe@forja.test');
    });

    it('registra instâncias prontas', function (): void {
        $container = new Container();
        $clock = new FixedClock('agora');
        $container->instance(ClockInterface::class, $clock);

        expect($container->get(ClockInterface::class))->toBe($clock);
    });

    it('substitui um singleton já resolvido ao registrar de novo', function (): void {
        $container = new Container();
        $container->singleton(ClockInterface::class, static fn (): FixedClock => new FixedClock('antes'));
        $container->get(ClockInterface::class);
        $container->singleton(ClockInterface::class, static fn (): FixedClock => new FixedClock('depois'));

        expect($container->get(ClockInterface::class)->now())->toBe('depois');
    });

    it('lista os bindings registrados', function (): void {
        $container = new Container();
        $container->bind(ClockInterface::class, FixedClock::class);
        $container->factory('fabrica', static fn (): int => 1);

        expect($container->bindings())->toBe([ClockInterface::class => FixedClock::class, 'fabrica' => null]);
    });
});

describe('make', function (): void {
    it('sobrescreve parâmetros por nome e por tipo', function (): void {
        $container = new Container();
        $clock = new FixedClock('override');

        $mailer = $container->make(Mailer::class, [Logger::class => new Logger($clock), 'from' => 'outro@forja.test']);
        $scalar = $container->make(NeedsScalar::class, ['dsn' => 'sqlite::memory:']);

        expect($mailer->from)->toBe('outro@forja.test')
            ->and($mailer->logger->clock)->toBe($clock)
            ->and($scalar->dsn)->toBe('sqlite::memory:');
    });

    it('sempre cria uma nova instância, mesmo para singletons', function (): void {
        $container = new Container();
        $container->singleton(ClockInterface::class, FixedClock::class);

        expect($container->make(ClockInterface::class))->not->toBe($container->get(ClockInterface::class));
    });

    it('repassa parâmetros às fábricas', function (): void {
        $container = new Container();
        $container->bind(ClockInterface::class, static fn (string $time): FixedClock => new FixedClock($time));

        expect($container->make(ClockInterface::class, ['time' => '08:00'])->now())->toBe('08:00');
    });
});

describe('call', function (): void {
    beforeEach(function (): void {
        $this->container = new Container();
        $this->container->instance(ClockInterface::class, new FixedClock('09:00'));
    });

    it('injeta dependências em closures', function (): void {
        $result = $this->container->call(static fn (ClockInterface $clock, string $suffix = '!'): string => $clock->now() . $suffix);

        expect($result)->toBe('09:00!');
    });

    it('chama métodos de instância e de classe resolvida pelo container', function (): void {
        expect($this->container->call([new Greeter(), 'greet'], ['name' => 'Caio']))->toBe('09:00: olá, Caio')
            ->and($this->container->call([Greeter::class, 'greet']))->toBe('09:00: olá, mundo')
            ->and($this->container->call(Greeter::class . '::greet'))->toBe('09:00: olá, mundo');
    });

    it('chama métodos estáticos sem instanciar a classe', function (): void {
        expect($this->container->call(Greeter::shout(...), ['text' => 'forja']))->toBe('FORJA');
    });

    it('chama objetos invocáveis e funções nomeadas', function (): void {
        expect($this->container->call(new Greeter()))->toBe('invocado em 09:00')
            ->and($this->container->call('strtoupper', ['string' => 'abc']))->toBe('ABC');
    });

    it('falha para callables inválidos', function (): void {
        $this->container->call([new Greeter(), 'inexistente']);
    })->throws(ContainerException::class);
});
