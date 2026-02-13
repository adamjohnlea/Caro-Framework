<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Auth\Application\Services\AuthenticationService;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthenticationService $authService,
    ) {
    }

    #[Override]
    public function handle(Request $request, callable $next): Response
    {
        if ($request->getMethod() === 'POST') {
            $token = (string) $request->request->get('_csrf_token', '');

            if (!$this->authService->validateCsrfToken($token)) {
                return new Response('Invalid CSRF token.', 403);
            }
        }

        return $next($request);
    }
}
