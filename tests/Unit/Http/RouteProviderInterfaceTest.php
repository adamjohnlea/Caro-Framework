<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\RouteProviderInterface;
use App\Http\Router;
use Override;
use PHPUnit\Framework\TestCase;

final class RouteProviderInterfaceTest extends TestCase
{
    public function test_provider_registers_routes(): void
    {
        $provider = new class () implements RouteProviderInterface {
            #[Override]
            public function routes(Router $router): void
            {
                $router->get('/test', 'TestController', 'index', 'test.index');
                $router->post('/test', 'TestController', 'store', 'test.store');
            }
        };

        $router = new Router();
        $provider->routes($router);

        $routes = $router->getRoutes();
        $this->assertNotNull($routes->get('test.index'));
        $this->assertNotNull($routes->get('test.store'));
    }

    public function test_multiple_providers_register_routes(): void
    {
        $providerA = new class () implements RouteProviderInterface {
            #[Override]
            public function routes(Router $router): void
            {
                $router->get('/a', 'AController', 'index', 'a.index');
            }
        };

        $providerB = new class () implements RouteProviderInterface {
            #[Override]
            public function routes(Router $router): void
            {
                $router->get('/b', 'BController', 'index', 'b.index');
            }
        };

        $router = new Router();
        $providerA->routes($router);
        $providerB->routes($router);

        $routes = $router->getRoutes();
        $this->assertNotNull($routes->get('a.index'));
        $this->assertNotNull($routes->get('b.index'));
    }
}
