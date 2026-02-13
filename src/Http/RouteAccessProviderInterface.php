<?php

declare(strict_types=1);

namespace App\Http;

interface RouteAccessProviderInterface
{
    /** @return array{public?: list<string>, admin?: list<string>} */
    public function routeAccess(): array;
}
