<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

final class DependencyResolutionException extends RuntimeException
{
    public static function parameterNotResolvable(
        string $parameterName,
        string $controllerClass,
        string $method,
    ): self {
        return new self(
            sprintf('Cannot resolve parameter "$%s" for %s::%s()', $parameterName, $controllerClass, $method),
        );
    }
}
