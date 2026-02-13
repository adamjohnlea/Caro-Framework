<?php

declare(strict_types=1);

namespace App\Http;

use App\Shared\Container\ContainerInterface;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ControllerDispatcher
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @param array<string, string> $parameters Route parameters including _controller, _method, _route
     */
    public function dispatch(array $parameters, Request $request): Response
    {
        $controllerClass = $parameters['_controller'];
        $method = $parameters['_method'];

        /** @var object $controller */
        $controller = $this->container->get($controllerClass);

        $reflection = new ReflectionMethod($controller, $method);
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if ($typeName === Request::class) {
                $args[] = $request;
                continue;
            }

            $name = $param->getName();

            if (isset($parameters[$name])) {
                $args[] = $typeName === 'int' ? (int) $parameters[$name] : $parameters[$name];
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            throw new RuntimeException(
                sprintf('Cannot resolve parameter "$%s" for %s::%s()', $name, $controllerClass, $method),
            );
        }

        /** @var Response */
        return $reflection->invoke($controller, ...$args);
    }
}
