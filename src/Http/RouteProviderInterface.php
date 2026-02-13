<?php

declare(strict_types=1);

namespace App\Http;

interface RouteProviderInterface
{
    public function routes(Router $router): void;
}
