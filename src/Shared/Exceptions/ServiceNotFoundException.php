<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

final class ServiceNotFoundException extends RuntimeException
{
    public static function service(string $serviceId): self
    {
        return new self(sprintf('Service "%s" is not registered in the container.', $serviceId));
    }
}
