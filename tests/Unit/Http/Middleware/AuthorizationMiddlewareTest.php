<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\AuthorizationMiddleware;
use App\Http\RouteAccessRegistry;
use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\Domain\Models\User;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Domain\ValueObjects\EmailAddress;
use App\Modules\Auth\Domain\ValueObjects\HashedPassword;
use App\Modules\Auth\Domain\ValueObjects\UserRole;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizationMiddlewareTest extends TestCase
{
    public function test_non_admin_route_passes_through(): void
    {
        $registry = new RouteAccessRegistry();
        $registry->addAdminPrefix('/users');

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $authService = new AuthenticationService($userRepo);

        $middleware = new AuthorizationMiddleware($authService, $registry);
        $request = Request::create('/dashboard');

        $response = $middleware->handle($request, static fn () => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_admin_route_forbidden_for_non_admin(): void
    {
        $registry = new RouteAccessRegistry();
        $registry->addAdminPrefix('/users');

        $now = new DateTimeImmutable();
        $viewer = new User(
            1,
            new EmailAddress('viewer@test.com'),
            HashedPassword::fromHash('hash'),
            UserRole::Viewer,
            $now,
            $now,
        );

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findById')->with(1)->willReturn($viewer);
        $authService = new AuthenticationService($userRepo);

        $_SESSION['user_id'] = 1;

        $middleware = new AuthorizationMiddleware($authService, $registry);
        $request = Request::create('/users');

        $response = $middleware->handle($request, static fn () => new Response('ok'));

        $this->assertSame(403, $response->getStatusCode());

        unset($_SESSION['user_id']);
    }

    public function test_admin_route_allowed_for_admin(): void
    {
        $registry = new RouteAccessRegistry();
        $registry->addAdminPrefix('/users');

        $now = new DateTimeImmutable();
        $admin = new User(
            1,
            new EmailAddress('admin@test.com'),
            HashedPassword::fromHash('hash'),
            UserRole::Admin,
            $now,
            $now,
        );

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findById')->with(1)->willReturn($admin);
        $authService = new AuthenticationService($userRepo);

        $_SESSION['user_id'] = 1;

        $middleware = new AuthorizationMiddleware($authService, $registry);
        $request = Request::create('/users');

        $response = $middleware->handle($request, static fn () => new Response('ok'));

        $this->assertSame('ok', $response->getContent());

        unset($_SESSION['user_id']);
    }
}
