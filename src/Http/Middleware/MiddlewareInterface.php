<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface MiddlewareInterface
{
    /**
     * @param callable(Request): Response $next
     */
    public function handle(Request $request, callable $next): Response;
}
