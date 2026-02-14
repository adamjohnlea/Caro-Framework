<?php

declare(strict_types=1);

namespace App\Modules\Queue\Domain\Exceptions;

use RuntimeException;

final class InvalidJobException extends RuntimeException
{
    public static function notAnInstance(string $jobClass): self
    {
        return new self(
            sprintf('Deserialized job of class "%s" is not an instance of JobInterface', $jobClass),
        );
    }
}
