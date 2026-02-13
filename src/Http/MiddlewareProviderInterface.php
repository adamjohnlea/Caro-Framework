<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\MiddlewareInterface;

interface MiddlewareProviderInterface
{
    /** @return list<MiddlewareInterface> */
    public function middleware(): array;
}
