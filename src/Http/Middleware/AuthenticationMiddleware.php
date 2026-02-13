<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\Domain\Models\User;
use Override;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticationMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private const array PUBLIC_ROUTES = [
        '/login',
        '/health',
    ];

    public function __construct(
        private AuthenticationService $authService,
    ) {
    }

    #[Override]
    public function handle(Request $request, callable $next): Response
    {
        $path = $request->getPathInfo();

        if ($this->isPublicRoute($path)) {
            return $next($request);
        }

        $user = $this->authService->getCurrentUser();

        if (!$user instanceof User) {
            return new RedirectResponse('/login');
        }

        return $next($request);
    }

    private function isPublicRoute(string $path): bool
    {
        return array_any(self::PUBLIC_ROUTES, fn ($route): bool => $path === $route);
    }
}
