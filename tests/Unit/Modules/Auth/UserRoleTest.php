<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth;

use App\Modules\Auth\Domain\ValueObjects\UserRole;
use PHPUnit\Framework\TestCase;

final class UserRoleTest extends TestCase
{
    public function test_admin_role_has_correct_value(): void
    {
        $this->assertSame('admin', UserRole::Admin->value);
    }

    public function test_viewer_role_has_correct_value(): void
    {
        $this->assertSame('viewer', UserRole::Viewer->value);
    }

    public function test_admin_label(): void
    {
        $this->assertSame('Admin', UserRole::Admin->label());
    }

    public function test_viewer_label(): void
    {
        $this->assertSame('Viewer', UserRole::Viewer->label());
    }

    public function test_admin_is_admin(): void
    {
        $this->assertTrue(UserRole::Admin->isAdmin());
    }

    public function test_viewer_is_not_admin(): void
    {
        $this->assertFalse(UserRole::Viewer->isAdmin());
    }

    public function test_can_create_from_string(): void
    {
        $admin = UserRole::from('admin');
        $viewer = UserRole::from('viewer');

        $this->assertSame(UserRole::Admin, $admin);
        $this->assertSame(UserRole::Viewer, $viewer);
    }

    public function test_try_from_returns_null_for_invalid(): void
    {
        $this->assertNull(UserRole::tryFrom('invalid'));
    }
}
