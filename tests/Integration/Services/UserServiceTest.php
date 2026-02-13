<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Modules\Auth\Application\Services\UserService;
use App\Modules\Auth\Domain\Models\User;
use App\Modules\Auth\Domain\ValueObjects\EmailAddress;
use App\Modules\Auth\Domain\ValueObjects\HashedPassword;
use App\Modules\Auth\Domain\ValueObjects\UserRole;
use App\Modules\Auth\Infrastructure\Repositories\SqliteUserRepository;
use App\Shared\Exceptions\ValidationException;
use DateTimeImmutable;
use Tests\TestCase;

final class UserServiceTest extends TestCase
{
    private UserService $userService;
    private SqliteUserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
        $this->userRepository = new SqliteUserRepository($this->database);
        $this->userService = new UserService($this->userRepository);
    }

    // Tests for create()

    public function test_create_successfully_creates_new_user(): void
    {
        $user = $this->userService->create('admin@example.com', 'password123', 'admin');

        $this->assertNotNull($user->getId());
        $this->assertSame('admin@example.com', $user->getEmail()->getValue());
        $this->assertSame(UserRole::Admin, $user->getRole());
        $this->assertTrue($user->getPassword()->verify('password123'));
        $this->assertInstanceOf(DateTimeImmutable::class, $user->getCreatedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $user->getUpdatedAt());
    }

    public function test_create_persists_user_to_database(): void
    {
        $user = $this->userService->create('viewer@example.com', 'password123', 'viewer');

        $found = $this->userRepository->findById($user->getId() ?? 0);

        $this->assertNotNull($found);
        $this->assertSame($user->getId(), $found->getId());
        $this->assertSame('viewer@example.com', $found->getEmail()->getValue());
        $this->assertSame(UserRole::Viewer, $found->getRole());
    }

    public function test_create_throws_exception_for_duplicate_email(): void
    {
        $this->userService->create('admin@example.com', 'password123', 'admin');

        $this->expectException(ValidationException::class);

        try {
            $this->userService->create('admin@example.com', 'different_password', 'viewer');
        } catch (ValidationException $e) {
            $this->assertTrue($e->hasFieldError('email'));
            $this->assertSame('A user with this email already exists', $e->getFieldError('email'));
            throw $e;
        }
    }

    public function test_create_throws_exception_for_invalid_email(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->userService->create('not-an-email', 'password123', 'admin');
        } catch (ValidationException $e) {
            $this->assertTrue($e->hasFieldError('email'));
            $this->assertSame('Invalid email format', $e->getFieldError('email'));
            throw $e;
        }
    }

    public function test_create_throws_exception_for_invalid_role(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->userService->create('admin@example.com', 'password123', 'invalid_role');
        } catch (ValidationException $e) {
            $this->assertTrue($e->hasFieldError('role'));
            $this->assertStringContainsString('Invalid role', $e->getFieldError('role') ?? '');
            throw $e;
        }
    }

    public function test_create_hashes_password_correctly(): void
    {
        $user = $this->userService->create('admin@example.com', 'my_secret_password', 'admin');

        $this->assertTrue($user->getPassword()->verify('my_secret_password'));
        $this->assertFalse($user->getPassword()->verify('wrong_password'));
    }

    // Tests for update()

    public function test_update_successfully_updates_email(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'admin');
        $userId = $user->getId() ?? 0;

        $updated = $this->userService->update($userId, email: 'new-email@example.com');

        $this->assertSame('new-email@example.com', $updated->getEmail()->getValue());
        $this->assertSame($userId, $updated->getId());
    }

    public function test_update_successfully_updates_password(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'admin');
        $userId = $user->getId() ?? 0;

        $updated = $this->userService->update($userId, password: 'new_password123');

        $this->assertTrue($updated->getPassword()->verify('new_password123'));
        $this->assertFalse($updated->getPassword()->verify('password123'));
    }

    public function test_update_successfully_updates_role(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'admin');
        $userId = $user->getId() ?? 0;

        $updated = $this->userService->update($userId, role: 'viewer');

        $this->assertSame(UserRole::Viewer, $updated->getRole());
    }

    public function test_update_can_update_multiple_fields_at_once(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'admin');
        $userId = $user->getId() ?? 0;

        $updated = $this->userService->update(
            $userId,
            email: 'new@example.com',
            password: 'new_password',
            role: 'viewer',
        );

        $this->assertSame('new@example.com', $updated->getEmail()->getValue());
        $this->assertTrue($updated->getPassword()->verify('new_password'));
        $this->assertSame(UserRole::Viewer, $updated->getRole());
    }

    public function test_update_updates_updated_at_timestamp(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'admin');
        $userId = $user->getId() ?? 0;
        $originalUpdatedAt = $user->getUpdatedAt();

        // Sleep briefly to ensure timestamp difference
        sleep(1); // 1 second to ensure timestamp difference

        $updated = $this->userService->update($userId, email: 'new@example.com');

        $this->assertGreaterThan(
            $originalUpdatedAt->getTimestamp(),
            $updated->getUpdatedAt()->getTimestamp(),
        );
    }

    public function test_update_does_not_change_fields_when_null_provided(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'admin');
        $userId = $user->getId() ?? 0;

        $updated = $this->userService->update($userId);

        $this->assertSame('admin@example.com', $updated->getEmail()->getValue());
        $this->assertSame(UserRole::Admin, $updated->getRole());
    }

    public function test_update_does_not_change_password_when_empty_string_provided(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'admin');
        $userId = $user->getId() ?? 0;

        $updated = $this->userService->update($userId, password: '');

        $this->assertTrue($updated->getPassword()->verify('password123'));
    }

    public function test_update_throws_exception_for_nonexistent_user(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('User not found');

        $this->userService->update(99999, email: 'new@example.com');
    }

    public function test_update_throws_exception_for_duplicate_email(): void
    {
        $this->createAndSaveUser('admin@example.com', 'admin');
        $user2 = $this->createAndSaveUser('viewer@example.com', 'viewer');
        $user2Id = $user2->getId() ?? 0;

        $this->expectException(ValidationException::class);

        try {
            $this->userService->update($user2Id, email: 'admin@example.com');
        } catch (ValidationException $e) {
            $this->assertTrue($e->hasFieldError('email'));
            $this->assertSame('A user with this email already exists', $e->getFieldError('email'));
            throw $e;
        }
    }

    public function test_update_allows_keeping_same_email(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'admin');
        $userId = $user->getId() ?? 0;

        // Should not throw duplicate email exception when updating other fields
        $updated = $this->userService->update(
            $userId,
            email: 'admin@example.com',
            role: 'viewer',
        );

        $this->assertSame('admin@example.com', $updated->getEmail()->getValue());
        $this->assertSame(UserRole::Viewer, $updated->getRole());
    }

    public function test_update_throws_exception_for_invalid_email(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'admin');
        $userId = $user->getId() ?? 0;

        $this->expectException(ValidationException::class);

        try {
            $this->userService->update($userId, email: 'not-an-email');
        } catch (ValidationException $e) {
            $this->assertTrue($e->hasFieldError('email'));
            $this->assertSame('Invalid email format', $e->getFieldError('email'));
            throw $e;
        }
    }

    public function test_update_throws_exception_for_invalid_role(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'admin');
        $userId = $user->getId() ?? 0;

        $this->expectException(ValidationException::class);

        try {
            $this->userService->update($userId, role: 'invalid_role');
        } catch (ValidationException $e) {
            $this->assertTrue($e->hasFieldError('role'));
            $this->assertStringContainsString('Invalid role', $e->getFieldError('role') ?? '');
            throw $e;
        }
    }

    // Tests for delete()

    public function test_delete_successfully_removes_user(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'admin');
        $userId = $user->getId() ?? 0;

        $this->userService->delete($userId);

        $found = $this->userRepository->findById($userId);
        $this->assertNull($found);
    }

    public function test_delete_throws_exception_for_nonexistent_user(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('User not found');

        $this->userService->delete(99999);
    }

    public function test_delete_does_not_affect_other_users(): void
    {
        $user1 = $this->createAndSaveUser('admin@example.com', 'admin');
        $user2 = $this->createAndSaveUser('viewer@example.com', 'viewer');
        $user1Id = $user1->getId() ?? 0;
        $user2Id = $user2->getId() ?? 0;

        $this->userService->delete($user1Id);

        $this->assertNull($this->userRepository->findById($user1Id));
        $this->assertNotNull($this->userRepository->findById($user2Id));
    }

    // Tests for findById()

    public function test_find_by_id_returns_existing_user(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'admin');
        $userId = $user->getId() ?? 0;

        $found = $this->userService->findById($userId);

        $this->assertNotNull($found);
        $this->assertSame($userId, $found->getId());
        $this->assertSame('admin@example.com', $found->getEmail()->getValue());
        $this->assertSame(UserRole::Admin, $found->getRole());
    }

    public function test_find_by_id_returns_null_for_nonexistent_user(): void
    {
        $found = $this->userService->findById(99999);

        $this->assertNull($found);
    }

    // Tests for findAll()

    public function test_find_all_returns_empty_array_when_no_users(): void
    {
        $users = $this->userService->findAll();

        $this->assertSame([], $users);
    }

    public function test_find_all_returns_all_users(): void
    {
        $this->createAndSaveUser('admin@example.com', 'admin');
        $this->createAndSaveUser('viewer@example.com', 'viewer');

        $users = $this->userService->findAll();

        $this->assertCount(2, $users);
        $this->assertContainsOnlyInstancesOf(User::class, $users);
    }

    public function test_find_all_returns_users_with_correct_data(): void
    {
        $this->createAndSaveUser('admin@example.com', 'admin');
        $this->createAndSaveUser('viewer@example.com', 'viewer');

        $users = $this->userService->findAll();

        $emails = array_map(fn (User $u) => $u->getEmail()->getValue(), $users);
        $this->assertContains('admin@example.com', $emails);
        $this->assertContains('viewer@example.com', $emails);
    }

    // Tests for resolveRole() (private method tested indirectly)

    public function test_resolve_role_accepts_admin_role(): void
    {
        $user = $this->userService->create('admin@example.com', 'password123', 'admin');

        $this->assertSame(UserRole::Admin, $user->getRole());
    }

    public function test_resolve_role_accepts_viewer_role(): void
    {
        $user = $this->userService->create('viewer@example.com', 'password123', 'viewer');

        $this->assertSame(UserRole::Viewer, $user->getRole());
    }

    public function test_resolve_role_rejects_invalid_role(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->userService->create('admin@example.com', 'password123', 'super_admin');
        } catch (ValidationException $e) {
            $this->assertTrue($e->hasFieldError('role'));
            $this->assertStringContainsString('Invalid role', $e->getFieldError('role') ?? '');
            throw $e;
        }
    }

    public function test_resolve_role_rejects_empty_role(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->userService->create('admin@example.com', 'password123', '');
        } catch (ValidationException $e) {
            $this->assertTrue($e->hasFieldError('role'));
            $this->assertStringContainsString('Invalid role', $e->getFieldError('role') ?? '');
            throw $e;
        }
    }

    /**
     * Helper method to create and save a test user
     */
    private function createAndSaveUser(string $email, string $role): User
    {
        $now = new DateTimeImmutable();
        $userRole = UserRole::from($role);

        $user = new User(
            id: null,
            email: new EmailAddress($email),
            password: HashedPassword::fromPlaintext('password123'),
            role: $userRole,
            createdAt: $now,
            updatedAt: $now,
        );

        return $this->userRepository->save($user);
    }
}
