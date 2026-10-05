<?php

declare(strict_types=1);

namespace Forja\Http\Emitter;

use Closure;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Emite a resposta pela SAPI do PHP (header() + saída padrão), lendo o corpo em blocos.
 */
final readonly class SapiEmitter implements EmitterInterface
{
    /** @var Closure(string, bool, int): void */
    private Closure $header;

    /** @var Closure(): bool */
    private Closure $headersSent;

    /**
     * Os parâmetros $header e $headersSent permitem substituir as funções nativas em testes.
     *
     * @param positive-int $chunkSize
     * @param (Closure(string, bool, int): void)|null $header
     * @param (Closure(): bool)|null $headersSent
     */
    public function __construct(
        private int $chunkSize = 8192,
        ?Closure $header = null,
        ?Closure $headersSent = null,
    ) {
        // @phpstan-ignore smaller.alwaysFalse (validação em tempo de execução)
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('O tamanho do bloco deve ser maior que zero.');
        }

        $this->header = $header ?? static function (string $line, bool $replace, int $code): void {
            header($line, $replace, $code);
        };
        $this->headersSent = $headersSent ?? static fn (): bool => headers_sent();
    }

    public function emit(ResponseInterface $response): void
    {
        $this->assertNoPreviousOutput();

        $this->emitHeaders($response);
        // O status vem depois dos headers para que um "Location" não o sobrescreva com 302.
        $this->emitStatusLine($response);

        if (! $this->isBodyless($response->getStatusCode())) {
            $this->emitBody($response);
        }
    }

    private function assertNoPreviousOutput(): void
    {
        if (($this->headersSent)()) {
            throw EmitterException::headersAlreadySent();
        }

        if (ob_get_level() > 0 && ob_get_length() > 0) {
            throw EmitterException::outputAlreadySent();
        }
    }

    private function emitHeaders(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();

        foreach ($response->getHeaders() as $name => $values) {
            // Set-Cookie não pode ser substituído: cada valor vira um header próprio.
            $replace = strtolower($name) !== 'set-cookie';

            foreach ($values as $value) {
                ($this->header)(sprintf('%s: %s', $name, $value), $replace, $status);
                $replace = false;
            }
        }
    }

    private function emitStatusLine(ResponseInterface $response): void
    {
        $reason = $response->getReasonPhrase();
        $status = $response->getStatusCode();

        ($this->header)(
            sprintf('HTTP/%s %d%s', $response->getProtocolVersion(), $status, $reason !== '' ? ' ' . $reason : ''),
            true,
            $status,
        );
    }

    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        if (! $body->isReadable()) {
            echo $body;

            return;
        }

        while (! $body->eof()) {
            $chunk = $body->read($this->chunkSize);

            if ($chunk === '') {
                break;
            }

            echo $chunk;
        }
    }

    private function isBodyless(int $status): bool
    {
        return $status < 200 || $status === 204 || $status === 304;
    }
}
