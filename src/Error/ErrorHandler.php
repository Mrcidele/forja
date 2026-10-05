<?php

declare(strict_types=1);

namespace Forja\Error;

use ErrorException;
use Forja\Http\Emitter\EmitterInterface;
use Forja\Http\RequestFactory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Handler global do PHP para o que escapa do ErrorHandlerMiddleware: erros
 * viram ErrorException, exceções não capturadas e erros fatais (no shutdown)
 * são relatados e convertidos em resposta pelo ExceptionHandlerInterface.
 */
final class ErrorHandler
{
    private const int FATAL_ERRORS = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

    private bool $registered = false;

    /** Nível de output buffering existente antes da aplicação; só os buffers acima dele são descartados. */
    private readonly int $bufferLevel;

    public function __construct(
        private readonly ExceptionHandlerInterface $handler,
        private readonly EmitterInterface $emitter,
        private readonly RequestFactory $requests = new RequestFactory(),
    ) {
        $this->bufferLevel = ob_get_level();
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;
        set_error_handler($this->handleError(...));
        set_exception_handler($this->handleException(...));
        register_shutdown_function($this->handleShutdown(...));
    }

    public function unregister(): void
    {
        if (! $this->registered) {
            return;
        }

        $this->registered = false;
        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * @throws ErrorException
     */
    public function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public function handleException(Throwable $exception): void
    {
        try {
            $this->handler->report($exception);
        } catch (Throwable) {
            // Uma falha ao relatar não pode impedir a resposta.
        }

        $this->emitter->emit($this->handler->render($exception, $this->currentRequest()));
    }

    public function handleShutdown(): void
    {
        if ($this->registered) {
            $this->handleFatalError(error_get_last());
        }
    }

    /**
     * @param array{type: int, message: string, file: string, line: int}|null $error
     */
    public function handleFatalError(?array $error): void
    {
        if ($error === null || ($error['type'] & self::FATAL_ERRORS) === 0) {
            return;
        }

        // Descarta saída parcial para que a página de erro saia limpa.
        while (ob_get_level() > $this->bufferLevel) {
            ob_end_clean();
        }

        $this->handleException(new FatalErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
    }

    private function currentRequest(): ServerRequestInterface
    {
        try {
            return $this->requests->fromGlobals();
        } catch (Throwable) {
            return new ServerRequest('GET', '/');
        }
    }
}
