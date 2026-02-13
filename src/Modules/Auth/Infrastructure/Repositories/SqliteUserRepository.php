<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Repositories;

use App\Database\Database;
use App\Modules\Auth\Domain\Models\User;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Domain\ValueObjects\EmailAddress;
use App\Modules\Auth\Domain\ValueObjects\HashedPassword;
use App\Modules\Auth\Domain\ValueObjects\UserRole;
use DateTimeImmutable;
use Override;

final readonly class SqliteUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private Database $database,
    ) {
    }

    #[Override]
    public function save(User $user): User
    {
        $this->database->query(
            'INSERT INTO users (email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [
                $user->getEmail()->getValue(),
                $user->getPassword()->getHash(),
                $user->getRole()->value,
                $user->getCreatedAt()->format('Y-m-d H:i:s'),
                $user->getUpdatedAt()->format('Y-m-d H:i:s'),
            ],
        );

        $lastId = $this->database->lastInsertId();
        if ($lastId !== false) {
            $user->setId((int) $lastId);
        }

        return $user;
    }

    #[Override]
    public function update(User $user): User
    {
        $this->database->query(
            'UPDATE users SET email = ?, password_hash = ?, role = ?, updated_at = ? WHERE id = ?',
            [
                $user->getEmail()->getValue(),
                $user->getPassword()->getHash(),
                $user->getRole()->value,
                $user->getUpdatedAt()->format('Y-m-d H:i:s'),
                $user->getId(),
            ],
        );

        return $user;
    }

    #[Override]
    public function findById(int $id): ?User
    {
        $stmt = $this->database->query('SELECT * FROM users WHERE id = ?', [$id]);

        /** @var array{id: string|int, email: string, password_hash: string, role: string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrateUser($row);
    }

    #[Override]
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->database->query('SELECT * FROM users WHERE email = ?', [strtolower($email)]);

        /** @var array{id: string|int, email: string, password_hash: string, role: string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrateUser($row);
    }

    /**
     * @return array<User>
     */
    #[Override]
    public function findAll(): array
    {
        $stmt = $this->database->query('SELECT * FROM users ORDER BY email ASC');

        /** @var array<array{id: string|int, email: string, password_hash: string, role: string, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        $users = [];
        foreach ($rows as $row) {
            $users[] = $this->hydrateUser($row);
        }

        return $users;
    }

    #[Override]
    public function delete(int $id): void
    {
        $this->database->query('DELETE FROM users WHERE id = ?', [$id]);
    }

    #[Override]
    public function count(): int
    {
        $stmt = $this->database->query('SELECT COUNT(*) as count FROM users');

        /** @var array{count: string|int} $row */
        $row = $stmt->fetch();

        return (int) $row['count'];
    }

    /**
     * @param array{id: string|int, email: string, password_hash: string, role: string, created_at: string, updated_at: string} $row
     */
    private function hydrateUser(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            email: new EmailAddress($row['email']),
            password: HashedPassword::fromHash($row['password_hash']),
            role: UserRole::from($row['role']),
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
