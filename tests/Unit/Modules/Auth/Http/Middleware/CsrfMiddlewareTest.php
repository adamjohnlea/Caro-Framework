<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Http\Middleware;

use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Http\Middleware\CsrfMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CsrfMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure clean session state for each test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_SESSION = [];
    }

    public function test_get_request_passes_through_without_csrf_validation(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $authService = new AuthenticationService($userRepo);

        $middleware = new CsrfMiddleware($authService);
        $request = Request::create('/dashboard', 'GET');

        $response = $middleware->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_post_request_with_valid_csrf_token_passes_through(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $authService = new AuthenticationService($userRepo);

        // Generate a CSRF token
        $csrfToken = $authService->getCsrfToken();

        $middleware = new CsrfMiddleware($authService);
        $request = Request::create('/dashboard', 'POST', [
            '_csrf_token' => $csrfToken,
        ]);

        $response = $middleware->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_post_request_with_invalid_csrf_token_returns_403(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $authService = new AuthenticationService($userRepo);

        // Generate a valid token to establish session
        $authService->getCsrfToken();

        $middleware = new CsrfMiddleware($authService);
        $request = Request::create('/dashboard', 'POST', [
            '_csrf_token' => 'invalid_token_value',
        ]);

        $response = $middleware->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame('Invalid CSRF token.', $response->getContent());
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_post_request_with_missing_csrf_token_returns_403(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $authService = new AuthenticationService($userRepo);

        $middleware = new CsrfMiddleware($authService);
        $request = Request::create('/dashboard', 'POST');

        $response = $middleware->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame('Invalid CSRF token.', $response->getContent());
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_post_request_with_empty_csrf_token_returns_403(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $authService = new AuthenticationService($userRepo);

        // Generate a valid token to establish session
        $authService->getCsrfToken();

        $middleware = new CsrfMiddleware($authService);
        $request = Request::create('/dashboard', 'POST', [
            '_csrf_token' => '',
        ]);

        $response = $middleware->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame('Invalid CSRF token.', $response->getContent());
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_post_request_with_numeric_csrf_token_returns_403(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $authService = new AuthenticationService($userRepo);

        // Generate a valid token to establish session
        $authService->getCsrfToken();

        $middleware = new CsrfMiddleware($authService);
        $request = Request::create('/dashboard', 'POST', [
            '_csrf_token' => 12345,
        ]);

        $response = $middleware->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame('Invalid CSRF token.', $response->getContent());
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_put_request_passes_through_without_csrf_validation(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $authService = new AuthenticationService($userRepo);

        $middleware = new CsrfMiddleware($authService);
        $request = Request::create('/dashboard', 'PUT');

        $response = $middleware->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_delete_request_passes_through_without_csrf_validation(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $authService = new AuthenticationService($userRepo);

        $middleware = new CsrfMiddleware($authService);
        $request = Request::create('/dashboard', 'DELETE');

        $response = $middleware->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_patch_request_passes_through_without_csrf_validation(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $authService = new AuthenticationService($userRepo);

        $middleware = new CsrfMiddleware($authService);
        $request = Request::create('/dashboard', 'PATCH');

        $response = $middleware->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_next_callable_is_not_called_on_invalid_csrf_token(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $authService = new AuthenticationService($userRepo);

        $middleware = new CsrfMiddleware($authService);
        $request = Request::create('/dashboard', 'POST', [
            '_csrf_token' => 'invalid',
        ]);

        $nextCalled = false;
        $response = $middleware->handle($request, static function () use (&$nextCalled): Response {
            $nextCalled = true;

            return new Response('ok');
        });

        $this->assertFalse($nextCalled, 'Next middleware should not be called on invalid CSRF token');
        $this->assertSame(403, $response->getStatusCode());
    }
}
