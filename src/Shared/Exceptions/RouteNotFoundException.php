<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

final class RouteNotFoundException extends RuntimeException
{
    public static function named(string $routeName): self
    {
        return new self(sprintf('Route "%s" not found.', $routeName));
    }
}
