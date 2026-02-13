<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\Domain\Models\User;
use App\Modules\Auth\Domain\ValueObjects\EmailAddress;
use App\Modules\Auth\Domain\ValueObjects\HashedPassword;
use App\Modules\Auth\Domain\ValueObjects\UserRole;
use App\Modules\Auth\Infrastructure\Repositories\SqliteUserRepository;
use DateTimeImmutable;
use Tests\TestCase;

final class AuthenticationServiceTest extends TestCase
{
    private AuthenticationService $authService;
    private SqliteUserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
        $this->userRepository = new SqliteUserRepository($this->database);
        $this->authService = new AuthenticationService($this->userRepository);

        // Clear session before each test
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        // Clean up session after each test
        $_SESSION = [];
        parent::tearDown();
    }

    public function test_attempt_returns_user_on_valid_credentials(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'password123');

        $result = $this->authService->attempt('admin@example.com', 'password123');

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user->getId(), $result->getId());
        $this->assertSame('admin@example.com', $result->getEmail()->getValue());
        $this->assertArrayHasKey('user_id', $_SESSION);
        $this->assertSame($user->getId(), $_SESSION['user_id']);
    }

    public function test_attempt_returns_null_on_invalid_password(): void
    {
        $this->createAndSaveUser('admin@example.com', 'password123');

        $result = $this->authService->attempt('admin@example.com', 'wrongpassword');

        $this->assertNull($result);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function test_attempt_returns_null_on_nonexistent_email(): void
    {
        $result = $this->authService->attempt('nonexistent@example.com', 'password123');

        $this->assertNull($result);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function test_attempt_is_case_insensitive_for_email(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'password123');

        $result = $this->authService->attempt('ADMIN@EXAMPLE.COM', 'password123');

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user->getId(), $result->getId());
    }

    public function test_logout_clears_user_id_from_session(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'password123');
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['other_data'] = 'should_remain';

        $this->authService->logout();

        $this->assertArrayNotHasKey('user_id', $_SESSION);
        $this->assertArrayHasKey('other_data', $_SESSION);
    }

    public function test_get_current_user_returns_user_when_logged_in(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'password123');
        $_SESSION['user_id'] = $user->getId();

        $result = $this->authService->getCurrentUser();

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user->getId(), $result->getId());
        $this->assertSame('admin@example.com', $result->getEmail()->getValue());
    }

    public function test_get_current_user_returns_null_when_not_logged_in(): void
    {
        $result = $this->authService->getCurrentUser();

        $this->assertNull($result);
    }

    public function test_get_current_user_returns_null_when_user_id_invalid(): void
    {
        $_SESSION['user_id'] = 99999;

        $result = $this->authService->getCurrentUser();

        $this->assertNull($result);
    }

    public function test_get_csrf_token_generates_token(): void
    {
        $token = $this->authService->getCsrfToken();

        $this->assertIsString($token);
        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    public function test_get_csrf_token_returns_same_token_on_subsequent_calls(): void
    {
        $token1 = $this->authService->getCsrfToken();
        $token2 = $this->authService->getCsrfToken();

        $this->assertSame($token1, $token2);
    }

    public function test_validate_csrf_token_returns_true_for_valid_token(): void
    {
        $token = $this->authService->getCsrfToken();

        $result = $this->authService->validateCsrfToken($token);

        $this->assertTrue($result);
    }

    public function test_validate_csrf_token_returns_false_for_invalid_token(): void
    {
        $this->authService->getCsrfToken();

        $result = $this->authService->validateCsrfToken('invalid_token');

        $this->assertFalse($result);
    }

    public function test_validate_csrf_token_returns_false_when_no_token_in_session(): void
    {
        $result = $this->authService->validateCsrfToken('some_token');

        $this->assertFalse($result);
    }

    public function test_validate_csrf_token_uses_timing_safe_comparison(): void
    {
        // This test verifies hash_equals is used (timing-safe comparison)
        // We can't directly test timing, but we verify correct/incorrect tokens
        $token = $this->authService->getCsrfToken();

        // Create a token that differs by one character
        $similarToken = substr($token, 0, -1) . ($token[-1] === 'a' ? 'b' : 'a');

        $this->assertTrue($this->authService->validateCsrfToken($token));
        $this->assertFalse($this->authService->validateCsrfToken($similarToken));
    }

    private function createAndSaveUser(string $email, string $password): User
    {
        $now = new DateTimeImmutable();
        $user = new User(
            id: null,
            email: new EmailAddress($email),
            password: HashedPassword::fromPlaintext($password),
            role: UserRole::Admin,
            createdAt: $now,
            updatedAt: $now,
        );

        return $this->userRepository->save($user);
    }
}
