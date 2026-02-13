<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Database\Database;
use App\Http\ControllerDispatcher;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Middleware\MiddlewarePipeline;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\MiddlewareProviderInterface;
use App\Http\RouteAccessProviderInterface;
use App\Http\RouteAccessRegistry;
use App\Http\RouteProviderInterface;
use App\Http\Router;
use App\Http\UrlGenerator;
use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\AuthServiceProvider;
use App\Modules\Auth\Domain\Models\User;
use App\Modules\Auth\Domain\ValueObjects\EmailAddress;
use App\Modules\Auth\Domain\ValueObjects\HashedPassword;
use App\Modules\Auth\Domain\ValueObjects\UserRole;
use App\Modules\Auth\Infrastructure\Repositories\SqliteUserRepository;
use App\Shared\Container\Container;
use App\Shared\Events\EventDispatcher;
use App\Shared\Events\EventDispatcherInterface;
use App\Shared\Session\FlashMessageService;
use App\Shared\Twig\AppExtension;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class UserCrudTest extends TestCase
{
    private Container $container;
    private Router $router;
    private MiddlewarePipeline $pipeline;
    private ControllerDispatcher $dispatcher;
    private SqliteUserRepository $userRepository;
    private AuthenticationService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();

        // Clear session
        $_SESSION = [];

        // Build config
        $config = [
            'app' => [
                'env' => 'testing',
                'debug' => true,
                'name' => 'Test App',
            ],
            'database' => [
                'driver' => 'sqlite',
                'path' => ':memory:',
            ],
            'modules' => [
                'auth' => true,
                'email' => false,
                'queue' => false,
            ],
        ];

        // Build container
        $this->container = new Container();

        // Core services
        $this->container->set(LoggerInterface::class, static fn (): LoggerInterface => new NullLogger());
        $this->container->set(Database::class, fn (): Database => $this->database);
        $this->container->set(EventDispatcherInterface::class, static fn (): EventDispatcherInterface => new EventDispatcher());

        $flashMessageService = new FlashMessageService();
        $this->container->set(FlashMessageService::class, static fn (): FlashMessageService => $flashMessageService);

        $urlGenerator = null;

        // Twig
        $this->container->set(Environment::class, static function () use ($config, $flashMessageService, &$urlGenerator): Environment {
            $loader = new FilesystemLoader(__DIR__ . '/../../src/Views');
            $twig = new Environment($loader, [
                'strict_variables' => true,
                'cache' => false,
            ]);

            $twig->addGlobal('appName', $config['app']['name']);
            $twig->addExtension(new AppExtension(
                __DIR__ . '/../../public',
                $flashMessageService,
                $urlGenerator,
                static fn (): string => $_SESSION['csrf_token'] ?? '',
            ));

            return $twig;
        });

        // Controllers
        $this->container->set(HomeController::class, function () use ($config): HomeController {
            $twig = $this->container->get(Environment::class);
            assert($twig instanceof Environment);

            return new HomeController($twig, $config['modules']);
        });

        $this->container->set(HealthController::class, function (): HealthController {
            $database = $this->container->get(Database::class);
            assert($database instanceof Database);

            return new HealthController($database);
        });

        // Route access registry
        $routeAccessRegistry = new RouteAccessRegistry();
        $routeAccessRegistry->addPublicRoute('/health');
        $this->container->set(RouteAccessRegistry::class, static fn (): RouteAccessRegistry => $routeAccessRegistry);

        // Register Auth module
        if ($config['modules']['auth']) {
            $this->container->registerProvider(new AuthServiceProvider($this->container, $config));
        }

        // Collect route access from providers
        foreach ($this->container->getProviders() as $provider) {
            if ($provider instanceof RouteAccessProviderInterface) {
                $access = $provider->routeAccess();
                foreach ($access['public'] ?? [] as $route) {
                    $routeAccessRegistry->addPublicRoute($route);
                }
                foreach ($access['admin'] ?? [] as $prefix) {
                    $routeAccessRegistry->addAdminPrefix($prefix);
                }
            }
        }

        // Build router
        $twig = $this->container->get(Environment::class);
        assert($twig instanceof Environment);
        $this->router = new Router($twig);

        // Core routes
        $this->router->get('/', HomeController::class, 'index', 'home');
        $this->router->get('/health', HealthController::class, 'check', 'health');

        // Load module routes
        foreach ($this->container->getProviders() as $provider) {
            if ($provider instanceof RouteProviderInterface) {
                $provider->routes($this->router);
            }
        }

        // URL Generator
        $urlGenerator = new UrlGenerator($this->router->getRoutes());
        $this->container->set(UrlGenerator::class, static fn (): UrlGenerator => $urlGenerator);

        // Boot providers
        $this->container->boot();

        // Build middleware pipeline
        $this->pipeline = new MiddlewarePipeline();
        $this->pipeline->add(new SecurityHeadersMiddleware($config));

        // Load module middleware
        foreach ($this->container->getProviders() as $provider) {
            if ($provider instanceof MiddlewareProviderInterface) {
                foreach ($provider->middleware() as $middleware) {
                    $this->pipeline->add($middleware);
                }
            }
        }

        // Controller dispatcher
        $this->dispatcher = new ControllerDispatcher($this->container);

        // Get auth service and repository for helper methods
        $this->userRepository = new SqliteUserRepository($this->database);
        $authService = $this->container->get(AuthenticationService::class);
        assert($authService instanceof AuthenticationService);
        $this->authService = $authService;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function test_admin_can_view_user_list(): void
    {
        $admin = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $this->loginAs($admin);

        $this->createAndSaveUser('user1@example.com', 'password123', UserRole::Viewer);
        $this->createAndSaveUser('user2@example.com', 'password123', UserRole::Viewer);

        $request = Request::create('/users', 'GET');
        $response = $this->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Users', $response->getContent());
        $this->assertStringContainsString('admin@example.com', $response->getContent());
        $this->assertStringContainsString('user1@example.com', $response->getContent());
        $this->assertStringContainsString('user2@example.com', $response->getContent());
    }

    public function test_admin_can_view_create_user_form(): void
    {
        $admin = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $this->loginAs($admin);

        $request = Request::create('/users/create', 'GET');
        $response = $this->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Add User', $response->getContent());
        $this->assertStringContainsString('Email', $response->getContent());
        $this->assertStringContainsString('Password', $response->getContent());
        $this->assertStringContainsString('Role', $response->getContent());
    }

    public function test_admin_can_create_new_user(): void
    {
        $admin = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $this->loginAs($admin);

        $request = Request::create('/users', 'POST', [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => 'viewer',
            '_csrf_token' => $this->authService->getCsrfToken(),
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/users', $response->headers->get('Location'));

        $user = $this->userRepository->findByEmail('newuser@example.com');
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('newuser@example.com', $user->getEmail()->getValue());
        $this->assertSame(UserRole::Viewer, $user->getRole());
    }

    public function test_create_user_with_invalid_email_shows_error(): void
    {
        $admin = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $this->loginAs($admin);

        $request = Request::create('/users', 'POST', [
            'email' => 'invalid-email',
            'password' => 'password123',
            'role' => 'viewer',
            '_csrf_token' => $this->authService->getCsrfToken(),
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Invalid email format', $response->getContent());
    }

    public function test_create_user_with_duplicate_email_shows_error(): void
    {
        $admin = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $this->loginAs($admin);
        $this->createAndSaveUser('existing@example.com', 'password123', UserRole::Viewer);

        $request = Request::create('/users', 'POST', [
            'email' => 'existing@example.com',
            'password' => 'password123',
            'role' => 'viewer',
            '_csrf_token' => $this->authService->getCsrfToken(),
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('A user with this email already exists', $response->getContent());
    }

    public function test_create_user_with_short_password_shows_error(): void
    {
        $admin = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $this->loginAs($admin);

        $request = Request::create('/users', 'POST', [
            'email' => 'newuser@example.com',
            'password' => 'short',
            'role' => 'viewer',
            '_csrf_token' => $this->authService->getCsrfToken(),
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Password must be at least 8 characters', $response->getContent());
    }

    public function test_admin_can_view_edit_user_form(): void
    {
        $admin = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $this->loginAs($admin);
        $user = $this->createAndSaveUser('user@example.com', 'password123', UserRole::Viewer);

        $request = Request::create('/users/' . $user->getId() . '/edit', 'GET');
        $response = $this->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Edit User', $response->getContent());
        $this->assertStringContainsString('user@example.com', $response->getContent());
    }

    public function test_admin_can_update_existing_user(): void
    {
        $admin = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $this->loginAs($admin);
        $user = $this->createAndSaveUser('user@example.com', 'password123', UserRole::Viewer);

        $request = Request::create('/users/' . $user->getId() . '/update', 'POST', [
            'email' => 'updated@example.com',
            'password' => '',
            'role' => 'admin',
            '_csrf_token' => $this->authService->getCsrfToken(),
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/users', $response->headers->get('Location'));

        $updatedUser = $this->userRepository->findById($user->getId());
        $this->assertInstanceOf(User::class, $updatedUser);
        $this->assertSame('updated@example.com', $updatedUser->getEmail()->getValue());
        $this->assertSame(UserRole::Admin, $updatedUser->getRole());
    }

    public function test_admin_can_update_user_password(): void
    {
        $admin = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $this->loginAs($admin);
        $user = $this->createAndSaveUser('user@example.com', 'password123', UserRole::Viewer);

        $request = Request::create('/users/' . $user->getId() . '/update', 'POST', [
            'email' => 'user@example.com',
            'password' => 'newpassword123',
            'role' => 'viewer',
            '_csrf_token' => $this->authService->getCsrfToken(),
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(302, $response->getStatusCode());

        $updatedUser = $this->userRepository->findById($user->getId());
        $this->assertInstanceOf(User::class, $updatedUser);
        $this->assertTrue($updatedUser->getPassword()->verify('newpassword123'));
        $this->assertFalse($updatedUser->getPassword()->verify('password123'));
    }

    public function test_update_user_with_invalid_email_shows_error(): void
    {
        $admin = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $this->loginAs($admin);
        $user = $this->createAndSaveUser('user@example.com', 'password123', UserRole::Viewer);

        $request = Request::create('/users/' . $user->getId() . '/update', 'POST', [
            'email' => 'invalid-email',
            'password' => '',
            'role' => 'viewer',
            '_csrf_token' => $this->authService->getCsrfToken(),
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Invalid email format', $response->getContent());
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $this->loginAs($admin);
        $user = $this->createAndSaveUser('user@example.com', 'password123', UserRole::Viewer);

        $request = Request::create('/users/' . $user->getId() . '/delete', 'POST', [
            '_csrf_token' => $this->authService->getCsrfToken(),
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/users', $response->headers->get('Location'));

        $deletedUser = $this->userRepository->findById($user->getId());
        $this->assertNull($deletedUser);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $this->loginAs($admin);

        $request = Request::create('/users/' . $admin->getId() . '/delete', 'POST', [
            '_csrf_token' => $this->authService->getCsrfToken(),
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('You cannot delete your own account', $response->getContent());

        $user = $this->userRepository->findById($admin->getId());
        $this->assertInstanceOf(User::class, $user);
    }

    public function test_viewer_cannot_access_user_list(): void
    {
        $viewer = $this->createAndSaveUser('viewer@example.com', 'password123', UserRole::Viewer);
        $this->loginAs($viewer);

        $request = Request::create('/users', 'GET');
        $response = $this->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Forbidden', $response->getContent());
    }

    public function test_viewer_cannot_access_create_user_form(): void
    {
        $viewer = $this->createAndSaveUser('viewer@example.com', 'password123', UserRole::Viewer);
        $this->loginAs($viewer);

        $request = Request::create('/users/create', 'GET');
        $response = $this->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Forbidden', $response->getContent());
    }

    public function test_viewer_cannot_create_user(): void
    {
        $viewer = $this->createAndSaveUser('viewer@example.com', 'password123', UserRole::Viewer);
        $this->loginAs($viewer);

        $request = Request::create('/users', 'POST', [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => 'viewer',
            '_csrf_token' => $this->authService->getCsrfToken(),
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Forbidden', $response->getContent());

        $user = $this->userRepository->findByEmail('newuser@example.com');
        $this->assertNull($user);
    }

    public function test_viewer_cannot_access_edit_user_form(): void
    {
        $viewer = $this->createAndSaveUser('viewer@example.com', 'password123', UserRole::Viewer);
        $this->loginAs($viewer);
        $user = $this->createAndSaveUser('user@example.com', 'password123', UserRole::Viewer);

        $request = Request::create('/users/' . $user->getId() . '/edit', 'GET');
        $response = $this->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Forbidden', $response->getContent());
    }

    public function test_viewer_cannot_update_user(): void
    {
        $viewer = $this->createAndSaveUser('viewer@example.com', 'password123', UserRole::Viewer);
        $this->loginAs($viewer);
        $user = $this->createAndSaveUser('user@example.com', 'password123', UserRole::Viewer);

        $request = Request::create('/users/' . $user->getId() . '/update', 'POST', [
            'email' => 'updated@example.com',
            'password' => '',
            'role' => 'admin',
            '_csrf_token' => $this->authService->getCsrfToken(),
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Forbidden', $response->getContent());

        $unchangedUser = $this->userRepository->findById($user->getId());
        $this->assertInstanceOf(User::class, $unchangedUser);
        $this->assertSame('user@example.com', $unchangedUser->getEmail()->getValue());
    }

    public function test_viewer_cannot_delete_user(): void
    {
        $viewer = $this->createAndSaveUser('viewer@example.com', 'password123', UserRole::Viewer);
        $this->loginAs($viewer);
        $user = $this->createAndSaveUser('user@example.com', 'password123', UserRole::Viewer);

        $request = Request::create('/users/' . $user->getId() . '/delete', 'POST', [
            '_csrf_token' => $this->authService->getCsrfToken(),
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Forbidden', $response->getContent());

        $unchangedUser = $this->userRepository->findById($user->getId());
        $this->assertInstanceOf(User::class, $unchangedUser);
    }

    public function test_validation_errors_display_per_field(): void
    {
        $admin = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $this->loginAs($admin);
        $this->createAndSaveUser('existing@example.com', 'password123', UserRole::Viewer);

        $request = Request::create('/users', 'POST', [
            'email' => 'existing@example.com',
            'password' => 'short',
            'role' => 'viewer',
            '_csrf_token' => $this->authService->getCsrfToken(),
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(422, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('A user with this email already exists', $content);
        $this->assertStringContainsString('Password must be at least 8 characters', $content);
    }

    private function dispatch(Request $request): Response
    {
        return $this->pipeline->handle(
            $request,
            fn (Request $request): Response => $this->router->dispatch(
                $request,
                fn (array $parameters, Request $request): Response => $this->dispatcher->dispatch($parameters, $request),
            ),
        );
    }

    private function createAndSaveUser(string $email, string $password, UserRole $role): User
    {
        $now = new DateTimeImmutable();
        $user = new User(
            id: null,
            email: new EmailAddress($email),
            password: HashedPassword::fromPlaintext($password),
            role: $role,
            createdAt: $now,
            updatedAt: $now,
        );

        return $this->userRepository->save($user);
    }

    private function loginAs(User $user): void
    {
        $_SESSION['user_id'] = $user->getId();
    }
}
