<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth;

use App\Modules\Auth\Domain\ValueObjects\HashedPassword;
use App\Shared\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class HashedPasswordTest extends TestCase
{
    public function test_from_plaintext_creates_hashed_password(): void
    {
        $password = HashedPassword::fromPlaintext('password123');

        $this->assertNotEmpty($password->getHash());
        $this->assertNotSame('password123', $password->getHash());
    }

    public function test_from_plaintext_throws_for_short_password(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters');

        HashedPassword::fromPlaintext('short');
    }

    public function test_from_plaintext_accepts_exactly_8_characters(): void
    {
        $password = HashedPassword::fromPlaintext('12345678');

        $this->assertNotEmpty($password->getHash());
    }

    public function test_verify_returns_true_for_correct_password(): void
    {
        $password = HashedPassword::fromPlaintext('password123');

        $this->assertTrue($password->verify('password123'));
    }

    public function test_verify_returns_false_for_incorrect_password(): void
    {
        $password = HashedPassword::fromPlaintext('password123');

        $this->assertFalse($password->verify('wrongpassword'));
    }

    public function test_from_hash_creates_password_from_existing_hash(): void
    {
        $original = HashedPassword::fromPlaintext('password123');
        $fromHash = HashedPassword::fromHash($original->getHash());

        $this->assertTrue($fromHash->verify('password123'));
    }

    public function test_from_hash_does_not_validate_length(): void
    {
        $password = HashedPassword::fromHash('somehash');

        $this->assertSame('somehash', $password->getHash());
    }
}
