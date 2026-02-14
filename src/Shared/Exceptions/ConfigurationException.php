<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

final class ConfigurationException extends RuntimeException
{
    public static function unsupportedDriver(string $driver): self
    {
        return new self(sprintf('Unsupported database driver: %s', $driver));
    }
}
