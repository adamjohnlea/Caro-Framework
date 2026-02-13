<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Services;

use App\Modules\Auth\Domain\Models\User;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Domain\ValueObjects\EmailAddress;
use App\Modules\Auth\Domain\ValueObjects\HashedPassword;
use App\Modules\Auth\Domain\ValueObjects\UserRole;
use App\Shared\Exceptions\ValidationException;
use DateTimeImmutable;

final readonly class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function create(string $email, string $password, string $role): User
    {
        $emailAddress = new EmailAddress($email);
        $userRole = $this->resolveRole($role);

        $existing = $this->userRepository->findByEmail($emailAddress->getValue());
        if ($existing instanceof User) {
            throw new ValidationException('A user with this email already exists');
        }

        $hashedPassword = HashedPassword::fromPlaintext($password);
        $now = new DateTimeImmutable();

        $user = new User(
            id: null,
            email: $emailAddress,
            password: $hashedPassword,
            role: $userRole,
            createdAt: $now,
            updatedAt: $now,
        );

        return $this->userRepository->save($user);
    }

    public function update(int $id, ?string $email = null, ?string $password = null, ?string $role = null): User
    {
        $user = $this->userRepository->findById($id);

        if (!$user instanceof User) {
            throw new ValidationException('User not found');
        }

        if ($email !== null) {
            $emailAddress = new EmailAddress($email);
            $existing = $this->userRepository->findByEmail($emailAddress->getValue());
            if ($existing instanceof User && $existing->getId() !== $id) {
                throw new ValidationException('A user with this email already exists');
            }
            $user->setEmail($emailAddress);
        }

        if ($password !== null && $password !== '') {
            $user->setPassword(HashedPassword::fromPlaintext($password));
        }

        if ($role !== null) {
            $user->setRole($this->resolveRole($role));
        }

        $user->setUpdatedAt(new DateTimeImmutable());

        return $this->userRepository->update($user);
    }

    public function delete(int $id): void
    {
        $user = $this->userRepository->findById($id);

        if (!$user instanceof User) {
            throw new ValidationException('User not found');
        }

        $this->userRepository->delete($id);
    }

    public function findById(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }

    /**
     * @return array<User>
     */
    public function findAll(): array
    {
        return $this->userRepository->findAll();
    }

    private function resolveRole(string $role): UserRole
    {
        $userRole = UserRole::tryFrom($role);

        if ($userRole === null) {
            throw new ValidationException('Invalid role: ' . $role);
        }

        return $userRole;
    }
}
