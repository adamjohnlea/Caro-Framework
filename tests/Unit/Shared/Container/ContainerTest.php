<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Container;

use App\Shared\Container\Container;
use App\Shared\Container\ContainerInterface;
use App\Shared\Exceptions\ServiceNotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as PsrContainerInterface;

final class ContainerTest extends TestCase
{
    public function test_implements_container_interface(): void
    {
        $container = new Container();

        $this->assertInstanceOf(ContainerInterface::class, $container);
    }

    public function test_implements_psr_container_interface(): void
    {
        $container = new Container();

        $this->assertInstanceOf(PsrContainerInterface::class, $container);
    }

    public function test_set_and_get(): void
    {
        $container = new Container();
        $container->set('foo', static fn (): string => 'bar');

        $this->assertSame('bar', $container->get('foo'));
    }

    public function test_has_returns_true_for_registered_service(): void
    {
        $container = new Container();
        $container->set('foo', static fn (): string => 'bar');

        $this->assertTrue($container->has('foo'));
    }

    public function test_has_returns_false_for_unknown_service(): void
    {
        $container = new Container();

        $this->assertFalse($container->has('foo'));
    }

    public function test_get_throws_on_unknown_service(): void
    {
        $container = new Container();

        $this->expectException(ServiceNotFoundException::class);
        $container->get('unknown');
    }

    public function test_get_providers_returns_registered_providers(): void
    {
        $container = new Container();

        $this->assertSame([], $container->getProviders());
    }
}
