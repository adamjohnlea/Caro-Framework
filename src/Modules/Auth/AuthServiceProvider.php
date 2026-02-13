<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Database\Database;
use App\Http\Middleware\AuthenticationMiddleware;
use App\Http\Middleware\AuthorizationMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Middleware\MiddlewareInterface;
use App\Http\MiddlewareProviderInterface;
use App\Http\RouteAccessProviderInterface;
use App\Http\RouteAccessRegistry;
use App\Http\RouteProviderInterface;
use App\Http\Router;
use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\Application\Services\UserService;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Auth\Http\Controllers\UserController;
use App\Modules\Auth\Infrastructure\Repositories\SqliteUserRepository;
use App\Shared\Providers\ServiceProvider;
use Override;
use Twig\Environment;

final class AuthServiceProvider extends ServiceProvider implements RouteProviderInterface, MiddlewareProviderInterface, RouteAccessProviderInterface
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
        /** @var Environment $twig */
        $twig = $this->container->get(Environment::class);
        /** @var AuthenticationService $authService */
        $authService = $this->container->get(AuthenticationService::class);

        $currentUser = $authService->getCurrentUser();
        $twig->addGlobal('currentUser', $currentUser);
        $twig->addGlobal('csrf_token', $authService->getCsrfToken());
        $twig->addGlobal('authEnabled', true);
    }

    #[Override]
    public function routes(Router $router): void
    {
        $router->get('/login', AuthController::class, 'showLogin', 'login');
        $router->post('/login', AuthController::class, 'login', 'login.post');
        $router->get('/logout', AuthController::class, 'logout', 'logout');

        $router->get('/users', UserController::class, 'index', 'users.index');
        $router->get('/users/create', UserController::class, 'create', 'users.create');
        $router->post('/users', UserController::class, 'store', 'users.store');
        $router->get('/users/{id}/edit', UserController::class, 'edit', 'users.edit');
        $router->post('/users/{id}/update', UserController::class, 'update', 'users.update');
        $router->post('/users/{id}/delete', UserController::class, 'destroy', 'users.destroy');
    }

    /** @return list<MiddlewareInterface> */
    #[Override]
    public function middleware(): array
    {
        /** @var AuthenticationService $authService */
        $authService = $this->container->get(AuthenticationService::class);
        /** @var RouteAccessRegistry $registry */
        $registry = $this->container->get(RouteAccessRegistry::class);

        return [
            new AuthenticationMiddleware($authService, $registry),
            new CsrfMiddleware($authService),
            new AuthorizationMiddleware($authService, $registry),
        ];
    }

    /** @return array{public: list<string>, admin: list<string>} */
    #[Override]
    public function routeAccess(): array
    {
        return [
            'public' => ['/login'],
            'admin' => ['/users'],
        ];
    }
}
