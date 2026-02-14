<?php

declare(strict_types=1);

namespace App\Http;

use App\Shared\Exceptions\RouteNotFoundException;
use App\Shared\Twig\UrlGeneratorInterface;
use Override;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final readonly class UrlGenerator implements UrlGeneratorInterface
{
    public function __construct(
        private RouteCollection $routes,
    ) {
    }

    /**
     * @param array<string, int|string> $params
     */
    #[Override]
    public function generate(string $name, array $params = []): string
    {
        $route = $this->routes->get($name);

        if (!$route instanceof Route) {
            throw RouteNotFoundException::named($name);
        }

        $path = $route->getPath();

        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
        }

        return $path;
    }
}
