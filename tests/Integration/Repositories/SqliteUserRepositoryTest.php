<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Modules\Auth\Domain\Models\User;
use App\Modules\Auth\Domain\ValueObjects\EmailAddress;
use App\Modules\Auth\Domain\ValueObjects\HashedPassword;
use App\Modules\Auth\Domain\ValueObjects\UserRole;
use App\Modules\Auth\Infrastructure\Repositories\SqliteUserRepository;
use DateTimeImmutable;
use PDOException;
use Tests\TestCase;

final class SqliteUserRepositoryTest extends TestCase
{
    private SqliteUserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
        $this->userRepository = new SqliteUserRepository($this->database);
    }

    public function test_save_persists_user_and_assigns_id(): void
    {
        $user = $this->createUser('admin@example.com', UserRole::Admin);

        $saved = $this->userRepository->save($user);

        $this->assertNotNull($saved->getId());
        $this->assertSame('admin@example.com', $saved->getEmail()->getValue());
        $this->assertSame(UserRole::Admin, $saved->getRole());
    }

    public function test_find_by_id_returns_user(): void
    {
        $saved = $this->userRepository->save(
            $this->createUser('admin@example.com', UserRole::Admin),
        );

        $found = $this->userRepository->findById($saved->getId() ?? 0);

        $this->assertNotNull($found);
        $this->assertSame($saved->getId(), $found->getId());
        $this->assertSame('admin@example.com', $found->getEmail()->getValue());
        $this->assertSame(UserRole::Admin, $found->getRole());
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $found = $this->userRepository->findById(999);

        $this->assertNull($found);
    }

    public function test_find_by_email_returns_user(): void
    {
        $this->userRepository->save(
            $this->createUser('admin@example.com', UserRole::Admin),
        );

        $found = $this->userRepository->findByEmail('admin@example.com');

        $this->assertNotNull($found);
        $this->assertSame('admin@example.com', $found->getEmail()->getValue());
    }

    public function test_find_by_email_returns_null_when_not_found(): void
    {
        $found = $this->userRepository->findByEmail('nonexistent@example.com');

        $this->assertNull($found);
    }

    public function test_find_by_email_is_case_insensitive(): void
    {
        $this->userRepository->save(
            $this->createUser('admin@example.com', UserRole::Admin),
        );

        $found = $this->userRepository->findByEmail('ADMIN@example.com');

        $this->assertNotNull($found);
    }

    public function test_find_all_returns_all_users(): void
    {
        $this->userRepository->save($this->createUser('admin@example.com', UserRole::Admin));
        $this->userRepository->save($this->createUser('viewer@example.com', UserRole::Viewer));

        $all = $this->userRepository->findAll();

        $this->assertCount(2, $all);
    }

    public function test_update_modifies_existing_user(): void
    {
        $saved = $this->userRepository->save(
            $this->createUser('admin@example.com', UserRole::Admin),
        );

        $saved->setRole(UserRole::Viewer);
        $saved->setEmail(new EmailAddress('updated@example.com'));
        $saved->setUpdatedAt(new DateTimeImmutable());

        $this->userRepository->update($saved);

        $found = $this->userRepository->findById($saved->getId() ?? 0);
        $this->assertNotNull($found);
        $this->assertSame('updated@example.com', $found->getEmail()->getValue());
        $this->assertSame(UserRole::Viewer, $found->getRole());
    }

    public function test_delete_removes_user(): void
    {
        $saved = $this->userRepository->save(
            $this->createUser('admin@example.com', UserRole::Admin),
        );

        $this->userRepository->delete($saved->getId() ?? 0);

        $this->assertNull($this->userRepository->findById($saved->getId() ?? 0));
    }

    public function test_count_returns_number_of_users(): void
    {
        $this->assertSame(0, $this->userRepository->count());

        $this->userRepository->save($this->createUser('admin@example.com', UserRole::Admin));
        $this->assertSame(1, $this->userRepository->count());

        $this->userRepository->save($this->createUser('viewer@example.com', UserRole::Viewer));
        $this->assertSame(2, $this->userRepository->count());
    }

    public function test_save_enforces_unique_email(): void
    {
        $this->userRepository->save($this->createUser('admin@example.com', UserRole::Admin));

        $this->expectException(PDOException::class);

        $this->userRepository->save($this->createUser('admin@example.com', UserRole::Viewer));
    }

    public function test_password_hash_is_persisted_correctly(): void
    {
        $user = $this->createUser('admin@example.com', UserRole::Admin);
        $this->userRepository->save($user);

        $found = $this->userRepository->findByEmail('admin@example.com');

        $this->assertNotNull($found);
        $this->assertTrue($found->getPassword()->verify('password123'));
    }

    private function createUser(string $email, UserRole $role): User
    {
        $now = new DateTimeImmutable();

        return new User(
            id: null,
            email: new EmailAddress($email),
            password: HashedPassword::fromPlaintext('password123'),
            role: $role,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
