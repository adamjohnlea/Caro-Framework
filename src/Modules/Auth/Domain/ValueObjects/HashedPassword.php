<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObjects;

use App\Shared\Exceptions\ValidationException;

final readonly class HashedPassword
{
    private function __construct(private string $hash)
    {
    }

    public static function fromPlaintext(string $password): self
    {
        if (strlen($password) < 8) {
            throw ValidationException::withFieldError('password', 'Password must be at least 8 characters');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        return new self($hash);
    }

    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }

    public function verify(string $password): bool
    {
        return password_verify($password, $this->hash);
    }

    public function getHash(): string
    {
        return $this->hash;
    }
}
