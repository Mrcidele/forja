<?php

declare(strict_types=1);

namespace Forja\Runtime;

use Closure;
use Forja\Error\ErrorHandler;
use Forja\Foundation\Application;
use Forja\Http\RequestFactory;
use RuntimeException;
use Throwable;

/**
 * Worker mode do FrankenPHP: a aplicação é inicializada uma vez e atende
 * várias requisições no mesmo processo.
 *
 * Entre requisições o estado compartilhado é limpo (ResettableInterface).
 * Evite guardar dados de requisição em singletons ou propriedades estáticas.
 */
final readonly class FrankenPhpRunner
{
    /** @var Closure(callable(): void): bool */
    private Closure $handleRequest;

    /**
     * @param int $maxRequests reinicia o worker após N requisições (0 = sem limite), contendo vazamentos de memória
     * @param (Closure(callable(): void): bool)|null $handleRequest substitui frankenphp_handle_request() em testes
     */
    public function __construct(
        private Application $app,
        private int $maxRequests = 0,
        ?Closure $handleRequest = null,
    ) {
        if (!$handleRequest instanceof \Closure && ! function_exists('frankenphp_handle_request')) {
            throw new RuntimeException('O worker mode requer o FrankenPHP (função frankenphp_handle_request indisponível).');
        }

        $this->handleRequest = $handleRequest ?? \frankenphp_handle_request(...);
    }

    /**
     * @return int requisições atendidas
     */
    public function run(): int
    {
        ignore_user_abort(true);
        $errors = $this->app->container->get(ErrorHandler::class);
        $errors->register();
        $handled = 0;

        // Uma exceção que escape do kernel não pode derrubar o worker.
        $handler = function () use ($errors): void {
            try {
                $this->app->send($this->app->container->get(RequestFactory::class)->fromGlobals());
            } catch (Throwable $exception) {
                $errors->handleException($exception);
            }
        };

        try {
            do {
                $keepRunning = ($this->handleRequest)($handler);
                $handled++;

                $this->app->resetState();
                gc_collect_cycles();
            } while ($keepRunning && ($this->maxRequests === 0 || $handled < $this->maxRequests));
        } finally {
            $errors->unregister();
        }

        return $handled;
    }
}
