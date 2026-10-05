<?php

declare(strict_types=1);

namespace Forja\Error;

use Forja\Http\Exception\HttpException;
use Forja\Http\Exception\UnprocessableEntityHttpException;
use Forja\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Converte exceções em respostas conforme o contexto:
 *
 * - JSON quando o cliente pede JSON (Accept) ou o caminho é de API;
 * - página de depuração com stack trace quando $debug está ativo;
 * - página genérica em produção, sem detalhes internos.
 *
 * HttpException define status, headers e mensagem; as demais viram 500.
 */
class ExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * @param list<string> $apiPrefixes caminhos que sempre recebem JSON
     */
    public function __construct(
        protected readonly bool $debug = false,
        protected readonly array $apiPrefixes = ['/api'],
        protected readonly ResponseFactory $responses = new ResponseFactory(),
        protected readonly DebugPageRenderer $debugPage = new DebugPageRenderer(),
        protected readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Registra no log as falhas do servidor; erros do cliente (HttpException
     * com status abaixo de 500) não são relatados.
     */
    public function report(Throwable $exception): void
    {
        if ($exception instanceof HttpException && $exception->getStatusCode() < 500) {
            return;
        }

        $this->logger?->error($exception->getMessage() !== '' ? $exception->getMessage() : $exception::class, ['exception' => $exception]);
    }

    public function render(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        $status = $exception instanceof HttpException ? $exception->getStatusCode() : 500;
        $headers = $exception instanceof HttpException ? $exception->getHeaders() : [];

        if ($this->wantsJson($request)) {
            return $this->responses->json($this->jsonPayload($exception, $status), $status, $headers);
        }

        if ($this->debug) {
            return $this->responses->html($this->debugPage->render($exception, $request, $status), $status, $headers);
        }

        return $this->responses->html($this->genericPage($status, $this->publicMessage($exception, $status)), $status, $headers);
    }

    public function wantsJson(ServerRequestInterface $request): bool
    {
        $accept = strtolower($request->getHeaderLine('Accept'));

        if (str_contains($accept, '/json') || str_contains($accept, '+json')) {
            return true;
        }

        $path = $request->getUri()->getPath();

        foreach ($this->apiPrefixes as $prefix) {
            $prefix = rtrim($prefix, '/');

            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mensagem segura para o cliente: a da HttpException ou uma genérica.
     */
    protected function publicMessage(Throwable $exception, int $status): string
    {
        if ($exception instanceof HttpException && $exception->getMessage() !== '') {
            return $exception->getMessage();
        }

        return $status >= 500 ? 'Erro interno do servidor.' : $this->reason($status);
    }

    /**
     * @return array{error: array<string, mixed>}
     */
    protected function jsonPayload(Throwable $exception, int $status): array
    {
        $error = ['status' => $status, 'message' => $this->publicMessage($exception, $status)];

        if ($exception instanceof UnprocessableEntityHttpException) {
            $error['errors'] = $exception->getErrors();
        }

        if ($this->debug) {
            $error['exception'] = $exception::class;
            $error['message'] = $exception->getMessage();
            $error['file'] = $exception->getFile();
            $error['line'] = $exception->getLine();
            $error['trace'] = array_map(
                static fn (array $frame): string => ($frame['file'] ?? '[interno]') . ':' . ($frame['line'] ?? 0) . ' ' . ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'] . '()',
                $exception->getTrace(),
            );
        }

        return ['error' => $error];
    }

    protected function genericPage(int $status, string $message): string
    {
        $title = $status . ' ' . $this->reason($status);

        return sprintf(
            <<<'HTML'
                <!doctype html>
                <html lang="pt-BR">
                <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>%1$s</title>
                <style>body{margin:0;min-height:100vh;display:grid;place-items:center;font:16px/1.5 system-ui,sans-serif;background:#fafafa;color:#27272a}main{text-align:center;padding:24px}h1{font-size:64px;margin:0;color:#a1a1aa}</style>
                </head>
                <body><main><h1>%2$d</h1><p>%3$s</p></main></body>
                </html>
                HTML,
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            $status,
            htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
        );
    }

    protected function reason(int $status): string
    {
        $reason = $this->responses->create($status)->getReasonPhrase();

        return $reason !== '' ? $reason : 'Erro';
    }
}
