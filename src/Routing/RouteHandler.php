<?php

declare(strict_types=1);

namespace Forja\Routing;

use Forja\Http\CallableHandler;
use Forja\Http\Exception\MethodNotAllowedHttpException;
use Forja\Http\Exception\NotFoundHttpException;
use Forja\Http\Middleware\Pipeline;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handler final da aplicação: casa a rota, executa os middlewares dela e o controller.
 *
 * Os parâmetros da rota e a própria Route ficam disponíveis como atributos
 * da requisição.
 */
final readonly class RouteHandler implements RequestHandlerInterface
{
    public function __construct(
        private Router $router,
        private ControllerInvoker $invoker,
        private ?ContainerInterface $container = null,
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

        $route = $result->route;
        $request = $request->withAttribute(Route::class, $route);

        foreach ($result->parameters as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        $controller = new CallableHandler(
            fn (ServerRequestInterface $request): ResponseInterface => $this->invoker->invoke($route, $request, $result->parameters),
        );

        return new Pipeline($route->middleware, $controller, $this->container)->handle($request);
    }
}
