<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Middleware;

use App\Http\Middleware\MiddlewareInterface;
use App\Http\RouteAccessRegistry;
use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\Domain\Models\User;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthenticationService $authService,
        private RouteAccessRegistry $routeAccessRegistry,
    ) {
    }

    #[Override]
    public function handle(Request $request, callable $next): Response
    {
        $path = $request->getPathInfo();

        if (!$this->isAdminRoute($path)) {
            return $next($request);
        }

        $user = $this->authService->getCurrentUser();

        if (!$user instanceof User || !$user->isAdmin()) {
            return new Response('Forbidden', 403);
        }

        return $next($request);
    }

    private function isAdminRoute(string $path): bool
    {
        return array_any(
            $this->routeAccessRegistry->getAdminPrefixes(),
            static fn (string $prefix): bool => str_starts_with($path, $prefix),
        );
    }
}
