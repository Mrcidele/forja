<?php

declare(strict_types=1);

namespace Forja\Routing;

use Forja\Http\Exception\MethodNotAllowedHttpException;
use Forja\Http\Exception\NotFoundHttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handler final da aplicação: casa a rota e executa o controller.
 *
 * Os parâmetros da rota e a própria Route ficam disponíveis como atributos
 * da requisição.
 */
final readonly class RouteHandler implements RequestHandlerInterface
{
    public function __construct(
        private Router $router,
        private ControllerInvoker $invoker,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $result = $this->router->match($request);

        if ($result->status === RouteStatus::MethodNotAllowed) {
            throw new MethodNotAllowedHttpException($result->allowedMethods);
        }

        if (! $result->isFound()) {
            throw new NotFoundHttpException(sprintf('Nenhuma rota para %s %s.', $request->getMethod(), $request->getUri()->getPath()));
        }

        $request = $request->withAttribute(Route::class, $result->route);

        foreach ($result->parameters as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        return $this->invoker->invoke($result->route, $request, $result->parameters);
    }
}
