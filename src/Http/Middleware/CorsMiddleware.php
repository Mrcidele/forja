<?php

declare(strict_types=1);

namespace Forja\Http\Middleware;

use Forja\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Responde preflights (OPTIONS) e adiciona os headers de CORS às respostas
 * de requisições de origens permitidas.
 */
final readonly class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CorsOptions $options = new CorsOptions(),
        private ResponseFactory $responses = new ResponseFactory(),
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        if ($this->isPreflight($request)) {
            $response = $this->responses->create(204);

            return $origin !== '' && $this->options->allowsOrigin($origin)
                ? $this->withPreflightHeaders($this->withOriginHeaders($response, $origin), $request)
                : $response;
        }

        $response = $handler->handle($request);

        if ($origin === '' || ! $this->options->allowsOrigin($origin)) {
            return $response;
        }

        $response = $this->withOriginHeaders($response, $origin);

        if ($this->options->exposedHeaders !== []) {
            return $response->withHeader('Access-Control-Expose-Headers', implode(', ', $this->options->exposedHeaders));
        }

        return $response;
    }

    private function isPreflight(ServerRequestInterface $request): bool
    {
        return $request->getMethod() === 'OPTIONS' && $request->hasHeader('Access-Control-Request-Method');
    }

    private function withOriginHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        // Com credenciais o navegador exige a origem exata em vez de "*".
        $wildcard = $this->options->allowsAnyOrigin() && ! $this->options->allowCredentials;
        $response = $response->withHeader('Access-Control-Allow-Origin', $wildcard ? '*' : $origin);

        if (! $wildcard) {
            $response = $response->withAddedHeader('Vary', 'Origin');
        }

        if ($this->options->allowCredentials) {
            return $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function withPreflightHeaders(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        $headers = in_array('*', $this->options->allowedHeaders, true)
            ? $request->getHeaderLine('Access-Control-Request-Headers')
            : implode(', ', $this->options->allowedHeaders);

        $response = $response->withHeader('Access-Control-Allow-Methods', implode(', ', $this->options->allowedMethods));

        if ($headers !== '') {
            $response = $response->withHeader('Access-Control-Allow-Headers', $headers)
                ->withAddedHeader('Vary', 'Access-Control-Request-Headers');
        }

        if ($this->options->maxAge > 0) {
            return $response->withHeader('Access-Control-Max-Age', (string) $this->options->maxAge);
        }

        return $response;
    }
}
