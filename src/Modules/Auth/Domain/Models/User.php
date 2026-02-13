<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Models;

use App\Modules\Auth\Domain\ValueObjects\EmailAddress;
use App\Modules\Auth\Domain\ValueObjects\HashedPassword;
use App\Modules\Auth\Domain\ValueObjects\UserRole;
use DateTimeImmutable;

final class User
{
    public function __construct(
        private ?int $id,
        private EmailAddress $email,
        private HashedPassword $password,
        private UserRole $role,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getEmail(): EmailAddress
    {
        return $this->email;
    }

    public function setEmail(EmailAddress $email): void
    {
        $this->email = $email;
    }

    public function getPassword(): HashedPassword
    {
        return $this->password;
    }

    public function setPassword(HashedPassword $password): void
    {
        $this->password = $password;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function setRole(UserRole $role): void
    {
        $this->role = $role;
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
