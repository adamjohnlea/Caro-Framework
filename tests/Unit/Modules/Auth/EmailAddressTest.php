<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth;

use App\Modules\Auth\Domain\ValueObjects\EmailAddress;
use App\Shared\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    public function test_can_be_created_with_valid_email(): void
    {
        $email = new EmailAddress('user@example.com');

        $this->assertSame('user@example.com', $email->getValue());
    }

    public function test_normalizes_to_lowercase(): void
    {
        $email = new EmailAddress('User@Example.COM');

        $this->assertSame('user@example.com', $email->getValue());
    }

    public function test_trims_whitespace(): void
    {
        $email = new EmailAddress('  user@example.com  ');

        $this->assertSame('user@example.com', $email->getValue());
    }

    public function test_throws_exception_for_empty_string(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Email cannot be empty');

        new EmailAddress('');
    }

    public function test_throws_exception_for_whitespace_only(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Email cannot be empty');

        new EmailAddress('   ');
    }

    public function test_throws_exception_for_invalid_email(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid email format');

        new EmailAddress('not-an-email');
    }

    public function test_throws_exception_for_email_without_domain(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid email format');

        new EmailAddress('user@');
    }

    public function test_equals_returns_true_for_same_email(): void
    {
        $email1 = new EmailAddress('user@example.com');
        $email2 = new EmailAddress('user@example.com');

        $this->assertTrue($email1->equals($email2));
    }

    public function test_equals_returns_true_for_case_different_email(): void
    {
        $email1 = new EmailAddress('user@example.com');
        $email2 = new EmailAddress('USER@Example.COM');

        $this->assertTrue($email1->equals($email2));
    }

    public function test_equals_returns_false_for_different_email(): void
    {
        $email1 = new EmailAddress('user@example.com');
        $email2 = new EmailAddress('other@example.com');

        $this->assertFalse($email1->equals($email2));
    }

    public function test_to_string_returns_value(): void
    {
        $email = new EmailAddress('user@example.com');

        $this->assertSame('user@example.com', (string) $email);
    }
}
