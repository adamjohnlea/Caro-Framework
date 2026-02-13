<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Services;

use App\Modules\Auth\Domain\Models\User;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;

final readonly class AuthenticationService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function attempt(string $email, string $password): ?User
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user instanceof User) {
            return null;
        }

        if (!$user->getPassword()->verify($password)) {
            return null;
        }

        /** @var array<string, mixed> $_SESSION */
        $_SESSION['user_id'] = $user->getId();

        return $user;
    }

    public function logout(): void
    {
        /** @var array<string, mixed> $_SESSION */
        unset($_SESSION['user_id']);
    }

    public function getCurrentUser(): ?User
    {
        /** @var array<string, mixed> $_SESSION */
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        /** @var int $userId */
        $userId = $_SESSION['user_id'];

        return $this->userRepository->findById($userId);
    }

    public function getCsrfToken(): string
    {
        /** @var array<string, mixed> $_SESSION */
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public function validateCsrfToken(string $token): bool
    {
        /** @var array<string, mixed> $_SESSION */
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
