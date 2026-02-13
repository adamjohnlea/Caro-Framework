<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObjects;

use App\Shared\Exceptions\ValidationException;
use Stringable;

final readonly class EmailAddress implements Stringable
{
    private string $value;

    public function __construct(string $email)
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            throw new ValidationException('Email cannot be empty');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Invalid email format');
        }

        $this->value = $email;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
