<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use InvalidArgumentException;

class ValidationException extends InvalidArgumentException
{
    /**
     * @var array<string, string>
     */
    private array $fieldErrors = [];

    /**
     * Create exception with field-specific errors
     *
     * @param array<string, string> $fieldErrors Map of field names to error messages
     */
    public static function withFieldErrors(array $fieldErrors, int $code = 0): self
    {
        $message = count($fieldErrors) > 0
            ? 'Validation failed for ' . count($fieldErrors) . ' field(s)'
            : 'Validation failed';

        $exception = new self($message, $code);
        $exception->fieldErrors = $fieldErrors;

        return $exception;
    }

    /**
     * Create exception with a single field error
     */
    public static function withFieldError(string $field, string $message, int $code = 0): self
    {
        return self::withFieldErrors([$field => $message], $code);
    }

    /**
     * Get all field errors
     *
     * @return array<string, string>
     */
    public function getFieldErrors(): array
    {
        return $this->fieldErrors;
    }

    /**
     * Check if a specific field has an error
     */
    public function hasFieldError(string $field): bool
    {
        return isset($this->fieldErrors[$field]);
    }

    /**
     * Get error message for a specific field
     */
    public function getFieldError(string $field): ?string
    {
        return $this->fieldErrors[$field] ?? null;
    }
}
