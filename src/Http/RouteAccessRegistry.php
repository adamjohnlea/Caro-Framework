<?php

declare(strict_types=1);

namespace App\Http;

final class RouteAccessRegistry
{
    /** @var list<string> */
    private array $publicRoutes = [];

    /** @var list<string> */
    private array $adminPrefixes = [];

    public function addPublicRoute(string $route): void
    {
        $this->publicRoutes[] = $route;
    }

    public function addAdminPrefix(string $prefix): void
    {
        $this->adminPrefixes[] = $prefix;
    }

    /** @return list<string> */
    public function getPublicRoutes(): array
    {
        return $this->publicRoutes;
    }

    /** @return list<string> */
    public function getAdminPrefixes(): array
    {
        return $this->adminPrefixes;
    }
}
