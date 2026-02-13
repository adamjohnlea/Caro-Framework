<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Exceptions;

use App\Shared\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class ValidationExceptionTest extends TestCase
{
    // Test backward compatibility with single error message

    public function test_can_be_created_with_simple_message(): void
    {
        $exception = new ValidationException('Simple error message');

        $this->assertSame('Simple error message', $exception->getMessage());
    }

    public function test_simple_message_has_no_field_errors(): void
    {
        $exception = new ValidationException('Simple error message');

        $this->assertSame([], $exception->getFieldErrors());
    }

    // Test field errors functionality

    public function test_can_be_created_with_field_errors(): void
    {
        $fieldErrors = [
            'email' => 'Invalid email format',
            'password' => 'Password must be at least 8 characters',
        ];

        $exception = ValidationException::withFieldErrors($fieldErrors);

        $this->assertSame($fieldErrors, $exception->getFieldErrors());
    }

    public function test_field_errors_message_summarizes_errors(): void
    {
        $fieldErrors = [
            'email' => 'Invalid email format',
            'password' => 'Password must be at least 8 characters',
        ];

        $exception = ValidationException::withFieldErrors($fieldErrors);

        $this->assertStringContainsString('Validation failed', $exception->getMessage());
    }

    public function test_single_field_error_message(): void
    {
        $fieldErrors = ['email' => 'Invalid email format'];

        $exception = ValidationException::withFieldErrors($fieldErrors);

        $this->assertStringContainsString('Validation failed', $exception->getMessage());
    }

    // Test hasFieldError()

    public function test_has_field_error_returns_true_when_field_exists(): void
    {
        $exception = ValidationException::withFieldErrors([
            'email' => 'Invalid email format',
        ]);

        $this->assertTrue($exception->hasFieldError('email'));
    }

    public function test_has_field_error_returns_false_when_field_does_not_exist(): void
    {
        $exception = ValidationException::withFieldErrors([
            'email' => 'Invalid email format',
        ]);

        $this->assertFalse($exception->hasFieldError('password'));
    }

    public function test_has_field_error_returns_false_for_simple_message(): void
    {
        $exception = new ValidationException('Simple error message');

        $this->assertFalse($exception->hasFieldError('email'));
    }

    // Test getFieldError()

    public function test_get_field_error_returns_error_message_when_field_exists(): void
    {
        $exception = ValidationException::withFieldErrors([
            'email' => 'Invalid email format',
            'password' => 'Password too short',
        ]);

        $this->assertSame('Invalid email format', $exception->getFieldError('email'));
        $this->assertSame('Password too short', $exception->getFieldError('password'));
    }

    public function test_get_field_error_returns_null_when_field_does_not_exist(): void
    {
        $exception = ValidationException::withFieldErrors([
            'email' => 'Invalid email format',
        ]);

        $this->assertNull($exception->getFieldError('password'));
    }

    public function test_get_field_error_returns_null_for_simple_message(): void
    {
        $exception = new ValidationException('Simple error message');

        $this->assertNull($exception->getFieldError('email'));
    }

    // Test edge cases

    public function test_empty_field_errors_array(): void
    {
        $exception = ValidationException::withFieldErrors([]);

        $this->assertSame([], $exception->getFieldErrors());
        $this->assertFalse($exception->hasFieldError('email'));
        $this->assertNull($exception->getFieldError('email'));
    }

    public function test_can_create_with_single_field_error(): void
    {
        $exception = ValidationException::withFieldError('email', 'Invalid email format');

        $this->assertTrue($exception->hasFieldError('email'));
        $this->assertSame('Invalid email format', $exception->getFieldError('email'));
        $this->assertSame(['email' => 'Invalid email format'], $exception->getFieldErrors());
    }
}
