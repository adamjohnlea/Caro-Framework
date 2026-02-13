<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObjects;

enum UserRole: string
{
    case Admin = 'admin';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Viewer => 'Viewer',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }
}
