<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment;

final readonly class Router
{
    private RouteCollection $routes;

    public function __construct(
        private ?Environment $twig = null,
    ) {
        $this->routes = new RouteCollection();
    }

    public function get(string $path, string $controller, string $method, string $name): void
    {
        $route = new Route($path, ['_controller' => $controller, '_method' => $method]);
        $route->setMethods(['GET']);
        $this->routes->add($name, $route);
    }

    public function post(string $path, string $controller, string $method, string $name): void
    {
        $route = new Route($path, ['_controller' => $controller, '_method' => $method]);
        $route->setMethods(['POST']);
        $this->routes->add($name, $route);
    }

    /**
     * @return array{_controller: string, _method: string, _route: string}&array<string, string>
     */
    public function match(Request $request): array
    {
        $context = new RequestContext();
        $context->fromRequest($request);

        $matcher = new UrlMatcher($this->routes, $context);

        /** @var array{_controller: string, _method: string, _route: string}&array<string, string> */
        return $matcher->match($request->getPathInfo());
    }

    /**
     * @param callable(array<string, mixed>, Request): Response $controllerResolver
     */
    public function dispatch(Request $request, callable $controllerResolver): Response
    {
        try {
            $parameters = $this->match($request);
            return $controllerResolver($parameters, $request);
        } catch (ResourceNotFoundException) {
            if ($this->twig instanceof Environment) {
                $content = $this->twig->render('errors/404.twig');

                return new Response($content, 404);
            }

            return new Response('Not Found', 404);
        } catch (MethodNotAllowedException) {
            return new Response('Method Not Allowed', 405);
        }
    }

    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }
}
