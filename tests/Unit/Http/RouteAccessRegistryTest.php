<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\RouteAccessRegistry;
use PHPUnit\Framework\TestCase;

final class RouteAccessRegistryTest extends TestCase
{
    public function test_add_and_get_public_routes(): void
    {
        $registry = new RouteAccessRegistry();
        $registry->addPublicRoute('/login');
        $registry->addPublicRoute('/health');

        $this->assertSame(['/login', '/health'], $registry->getPublicRoutes());
    }

    public function test_add_and_get_admin_prefixes(): void
    {
        $registry = new RouteAccessRegistry();
        $registry->addAdminPrefix('/users');
        $registry->addAdminPrefix('/admin');

        $this->assertSame(['/users', '/admin'], $registry->getAdminPrefixes());
    }

    public function test_empty_by_default(): void
    {
        $registry = new RouteAccessRegistry();

        $this->assertSame([], $registry->getPublicRoutes());
        $this->assertSame([], $registry->getAdminPrefixes());
    }
}
