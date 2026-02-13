<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class MiddlewarePipeline
{
    /** @var list<MiddlewareInterface> */
    private array $middleware = [];

    public function add(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    /**
     * @param callable(Request): Response $handler
     */
    public function handle(Request $request, callable $handler): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            static fn (callable $next, MiddlewareInterface $middleware): Closure =>
                static fn (Request $request): Response => $middleware->handle($request, $next),
            $handler,
        );

        return $pipeline($request);
    }
}
