<?php

declare(strict_types=1);

namespace Forja\Console;

use Forja\Console\Command\CacheClearCommand;
use Forja\Console\Command\ConfigCacheCommand;
use Forja\Console\Command\MakeControllerCommand;
use Forja\Console\Command\MakeEntityCommand;
use Forja\Console\Command\MakeMiddlewareCommand;
use Forja\Console\Command\MakeMigrationCommand;
use Forja\Console\Command\MigrateCommand;
use Forja\Console\Command\MigrateResetCommand;
use Forja\Console\Command\MigrateRollbackCommand;
use Forja\Console\Command\MigrateStatusCommand;
use Forja\Console\Command\OptimizeCommand;
use Forja\Console\Command\RouteCacheCommand;
use Forja\Console\Command\RouteListCommand;
use Forja\Foundation\Application;
use InvalidArgumentException;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Monta a aplicação de console (symfony/console) com os comandos do
 * framework e os registrados em config('console.commands').
 */
final class Kernel
{
    /** @var list<class-string<Command>> */
    public const array COMMANDS = [
        MigrateCommand::class,
        MigrateRollbackCommand::class,
        MigrateStatusCommand::class,
        MigrateResetCommand::class,
        MakeControllerCommand::class,
        MakeMiddlewareCommand::class,
        MakeMigrationCommand::class,
        MakeEntityCommand::class,
        RouteListCommand::class,
        RouteCacheCommand::class,
        ConfigCacheCommand::class,
        CacheClearCommand::class,
        OptimizeCommand::class,
    ];

    private ?ConsoleApplication $console = null;

    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function console(): ConsoleApplication
    {
        if ($this->console instanceof \Symfony\Component\Console\Application) {
            return $this->console;
        }

        $console = new ConsoleApplication('Forja', Application::VERSION);
        $console->setAutoExit(false);

        $commands = [];

        foreach ([...self::COMMANDS, ...$this->app->config()->array('console.commands', [])] as $class) {
            if (! is_string($class) || ! is_subclass_of($class, Command::class)) {
                throw new InvalidArgumentException(sprintf('Comando inválido em console.commands: [%s].', is_string($class) ? $class : get_debug_type($class)));
            }

            $commands[] = $this->app->container->get($class);
        }

        $console->addCommands($commands);

        return $this->console = $console;
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        return $this->console()->run($input, $output);
    }
}
