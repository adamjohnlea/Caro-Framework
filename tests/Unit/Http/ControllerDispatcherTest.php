<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\ControllerDispatcher;
use App\Shared\Container\Container;
use App\Shared\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ControllerDispatcherTest extends TestCase
{
    private ContainerInterface $container;
    private ControllerDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        $this->dispatcher = new ControllerDispatcher($this->container);
    }

    public function test_dispatch_with_no_params(): void
    {
        $controller = new class () {
            public function index(): Response
            {
                return new Response('ok');
            }
        };

        $this->container->set($controller::class, static fn () => $controller);

        $response = $this->dispatcher->dispatch(
            ['_controller' => $controller::class, '_method' => 'index', '_route' => 'test'],
            Request::create('/'),
        );

        $this->assertSame('ok', $response->getContent());
    }

    public function test_dispatch_with_request(): void
    {
        $controller = new class () {
            public function store(Request $request): Response
            {
                return new Response('method:' . $request->getMethod());
            }
        };

        $this->container->set($controller::class, static fn () => $controller);

        $response = $this->dispatcher->dispatch(
            ['_controller' => $controller::class, '_method' => 'store', '_route' => 'test'],
            Request::create('/', 'POST'),
        );

        $this->assertSame('method:POST', $response->getContent());
    }

    public function test_dispatch_with_int_route_param(): void
    {
        $controller = new class () {
            public function edit(int $id): Response
            {
                return new Response('id:' . $id);
            }
        };

        $this->container->set($controller::class, static fn () => $controller);

        $response = $this->dispatcher->dispatch(
            ['_controller' => $controller::class, '_method' => 'edit', '_route' => 'test', 'id' => '42'],
            Request::create('/test/42'),
        );

        $this->assertSame('id:42', $response->getContent());
    }

    public function test_dispatch_with_request_and_route_param(): void
    {
        $controller = new class () {
            public function update(int $id, Request $request): Response
            {
                return new Response('id:' . $id . ',method:' . $request->getMethod());
            }
        };

        $this->container->set($controller::class, static fn () => $controller);

        $response = $this->dispatcher->dispatch(
            ['_controller' => $controller::class, '_method' => 'update', '_route' => 'test', 'id' => '7'],
            Request::create('/test/7', 'POST'),
        );

        $this->assertSame('id:7,method:POST', $response->getContent());
    }

    public function test_dispatch_with_default_value(): void
    {
        $controller = new class () {
            public function list(string $format = 'html'): Response
            {
                return new Response('format:' . $format);
            }
        };

        $this->container->set($controller::class, static fn () => $controller);

        $response = $this->dispatcher->dispatch(
            ['_controller' => $controller::class, '_method' => 'list', '_route' => 'test'],
            Request::create('/'),
        );

        $this->assertSame('format:html', $response->getContent());
    }
}
