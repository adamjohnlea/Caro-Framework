<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Database\Database;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\Application\Services\UserService;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Infrastructure\Repositories\SqliteUserRepository;
use App\Shared\Providers\ServiceProvider;
use Override;
use Twig\Environment;

final class AuthServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->container->set(UserRepositoryInterface::class, function (): SqliteUserRepository {
            /** @var Database $database */
            $database = $this->container->get(Database::class);

            return new SqliteUserRepository($database);
        });

        $this->container->set(AuthenticationService::class, function (): AuthenticationService {
            /** @var UserRepositoryInterface $userRepository */
            $userRepository = $this->container->get(UserRepositoryInterface::class);

            return new AuthenticationService($userRepository);
        });

        $this->container->set(UserService::class, function (): UserService {
            /** @var UserRepositoryInterface $userRepository */
            $userRepository = $this->container->get(UserRepositoryInterface::class);

            return new UserService($userRepository);
        });

        $this->container->set(AuthController::class, function (): AuthController {
            /** @var AuthenticationService $authService */
            $authService = $this->container->get(AuthenticationService::class);
            /** @var Environment $twig */
            $twig = $this->container->get(Environment::class);

            return new AuthController($authService, $twig);
        });

        $this->container->set(UserController::class, function (): UserController {
            /** @var UserService $userService */
            $userService = $this->container->get(UserService::class);
            /** @var AuthenticationService $authService */
            $authService = $this->container->get(AuthenticationService::class);
            /** @var Environment $twig */
            $twig = $this->container->get(Environment::class);

            return new UserController($userService, $authService, $twig);
        });
    }

    #[Override]
    public function boot(): void
    {
        // Add auth globals to Twig
        /** @var Environment $twig */
        $twig = $this->container->get(Environment::class);
        /** @var AuthenticationService $authService */
        $authService = $this->container->get(AuthenticationService::class);

        $currentUser = $authService->getCurrentUser();
        $twig->addGlobal('currentUser', $currentUser);
        $twig->addGlobal('csrf_token', $authService->getCsrfToken());
        $twig->addGlobal('authEnabled', true);
    }
}
