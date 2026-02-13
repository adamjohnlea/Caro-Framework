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
        $this->database->table('users')->insert([
            'email' => $user->getEmail()->getValue(),
            'password_hash' => $user->getPassword()->getHash(),
            'role' => $user->getRole()->value,
            'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);

        $lastId = $this->database->lastInsertId();
        if ($lastId !== false) {
            $user->setId((int) $lastId);
        }

        return $user;
    }

    #[Override]
    public function update(User $user): User
    {
        $this->database->table('users')
            ->where('id', $user->getId(), '=')
            ->update([
                'email' => $user->getEmail()->getValue(),
                'password_hash' => $user->getPassword()->getHash(),
                'role' => $user->getRole()->value,
                'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
            ]);

        return $user;
    }

    #[Override]
    public function findById(int $id): ?User
    {
        $rows = $this->database->table('users')
            ->where('id', $id, '=')
            ->get();

        if (count($rows) === 0) {
            return null;
        }

        /** @var array{id: string|int, email: string, password_hash: string, role: string, created_at: string, updated_at: string} $row */
        $row = $rows[0];

        return $this->hydrateUser($row);
    }

    #[Override]
    public function findByEmail(string $email): ?User
    {
        $rows = $this->database->table('users')
            ->where('email', strtolower($email), '=')
            ->get();

        if (count($rows) === 0) {
            return null;
        }

        /** @var array{id: string|int, email: string, password_hash: string, role: string, created_at: string, updated_at: string} $row */
        $row = $rows[0];

        return $this->hydrateUser($row);
    }

    /**
     * @return array<User>
     */
    #[Override]
    public function findAll(): array
    {
        /** @var array<array{id: string|int, email: string, password_hash: string, role: string, created_at: string, updated_at: string}> $rows */
        $rows = $this->database->table('users')
            ->orderBy('email', 'ASC')
            ->get();

        $users = [];
        foreach ($rows as $row) {
            $users[] = $this->hydrateUser($row);
        }

        return $users;
    }

    #[Override]
    public function delete(int $id): void
    {
        $this->database->table('users')
            ->where('id', $id, '=')
            ->delete();
    }

    #[Override]
    public function count(): int
    {
        $rows = $this->database->table('users')
            ->select(['COUNT(*) as count'])
            ->get();

        /** @var array{count: string|int} $row */
        $row = $rows[0];

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
