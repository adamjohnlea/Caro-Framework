<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;",
        );
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=()',
        );

        // HSTS header for production environments
        /** @var array{env?: string} $appConfig */
        $appConfig = $this->config['app'] ?? [];
        if (($appConfig['env'] ?? 'development') === 'production') {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload',
            );
        }

        return $response;
    }
}
