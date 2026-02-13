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

        // Regenerate session ID to prevent session fixation attacks
        // This invalidates the old session ID and creates a new one
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        /** @var array<string, mixed> $_SESSION */
        $_SESSION['user_id'] = $user->getId();

        return $user;
    }

    public function logout(): void
    {
        /** @var array<string, mixed> $_SESSION */
        unset($_SESSION['user_id']);

        // Completely destroy the session to prevent session reuse
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
            // Regenerate ID for the new (empty) session that will be created
            session_regenerate_id(true);
        }
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
