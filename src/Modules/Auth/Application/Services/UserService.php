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
        $fieldErrors = [];

        // Validate email
        try {
            $emailAddress = new EmailAddress($email);
        } catch (ValidationException $e) {
            $fieldErrors = array_merge($fieldErrors, $e->getFieldErrors());
            $emailAddress = null;
        }

        // Validate role
        try {
            $userRole = $this->resolveRole($role);
        } catch (ValidationException $e) {
            $fieldErrors['role'] = $e->getMessage();
            $userRole = null;
        }

        // Validate password
        try {
            $hashedPassword = HashedPassword::fromPlaintext($password);
        } catch (ValidationException $e) {
            $fieldErrors = array_merge($fieldErrors, $e->getFieldErrors());
            $hashedPassword = null;
        }

        // Check for duplicate email
        if ($emailAddress !== null) {
            $existing = $this->userRepository->findByEmail($emailAddress->getValue());
            if ($existing instanceof User) {
                $fieldErrors['email'] = 'A user with this email already exists';
            }
        }

        // If there are any validation errors, throw aggregated exception
        if (count($fieldErrors) > 0) {
            throw ValidationException::withFieldErrors($fieldErrors);
        }

        // PHPStan: At this point, validation passed, so all variables are non-null
        assert($emailAddress instanceof EmailAddress);
        assert($hashedPassword instanceof HashedPassword);
        assert($userRole instanceof UserRole);

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

        $fieldErrors = [];
        $emailAddress = null;
        $hashedPassword = null;
        $userRole = null;

        // Validate email
        if ($email !== null) {
            try {
                $emailAddress = new EmailAddress($email);
                $existing = $this->userRepository->findByEmail($emailAddress->getValue());
                if ($existing instanceof User && $existing->getId() !== $id) {
                    $fieldErrors['email'] = 'A user with this email already exists';
                }
            } catch (ValidationException $e) {
                $fieldErrors = array_merge($fieldErrors, $e->getFieldErrors());
            }
        }

        // Validate password
        if ($password !== null && $password !== '') {
            try {
                $hashedPassword = HashedPassword::fromPlaintext($password);
            } catch (ValidationException $e) {
                $fieldErrors = array_merge($fieldErrors, $e->getFieldErrors());
            }
        }

        // Validate role
        if ($role !== null) {
            try {
                $userRole = $this->resolveRole($role);
            } catch (ValidationException $e) {
                $fieldErrors['role'] = $e->getMessage();
            }
        }

        // If there are any validation errors, throw aggregated exception
        if (count($fieldErrors) > 0) {
            throw ValidationException::withFieldErrors($fieldErrors);
        }

        // Apply validated changes
        if ($emailAddress !== null) {
            $user->setEmail($emailAddress);
        }

        if ($hashedPassword !== null) {
            $user->setPassword($hashedPassword);
        }

        if ($userRole !== null) {
            $user->setRole($userRole);
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
