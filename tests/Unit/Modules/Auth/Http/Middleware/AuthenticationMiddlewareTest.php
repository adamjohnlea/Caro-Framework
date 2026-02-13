<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Http\Middleware;

use App\Http\RouteAccessRegistry;
use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\Domain\Models\User;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Domain\ValueObjects\EmailAddress;
use App\Modules\Auth\Domain\ValueObjects\HashedPassword;
use App\Modules\Auth\Domain\ValueObjects\UserRole;
use App\Modules\Auth\Http\Middleware\AuthenticationMiddleware;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticationMiddlewareTest extends TestCase
{
    public function test_public_route_passes_through(): void
    {
        $registry = new RouteAccessRegistry();
        $registry->addPublicRoute('/login');

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $authService = new AuthenticationService($userRepo);

        $middleware = new AuthenticationMiddleware($authService, $registry);
        $request = Request::create('/login');

        $response = $middleware->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_unauthenticated_user_redirected(): void
    {
        $registry = new RouteAccessRegistry();

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $authService = new AuthenticationService($userRepo);

        // Ensure no user in session
        unset($_SESSION['user_id']);

        $middleware = new AuthenticationMiddleware($authService, $registry);
        $request = Request::create('/dashboard');

        $response = $middleware->handle($request, static fn (): Response => new Response('ok'));

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function test_authenticated_user_passes_through(): void
    {
        $registry = new RouteAccessRegistry();

        $now = new DateTimeImmutable();
        $user = new User(
            1,
            new EmailAddress('test@test.com'),
            HashedPassword::fromHash('hash'),
            UserRole::Admin,
            $now,
            $now,
        );

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findById')->with(1)->willReturn($user);
        $authService = new AuthenticationService($userRepo);

        $_SESSION['user_id'] = 1;

        $middleware = new AuthenticationMiddleware($authService, $registry);
        $request = Request::create('/dashboard');

        $response = $middleware->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame('ok', $response->getContent());

        unset($_SESSION['user_id']);
    }
}
