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

final class AuthFlowTest extends TestCase
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

    public function test_login_with_valid_credentials_redirects_to_home(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $csrfToken = $this->authService->getCsrfToken();

        $request = Request::create('/login', 'POST', [
            'email' => 'admin@example.com',
            'password' => 'password123',
            '_csrf_token' => $csrfToken,
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/', $response->headers->get('Location'));
        $this->assertArrayHasKey('user_id', $_SESSION);
        $this->assertSame($user->getId(), $_SESSION['user_id']);
    }

    public function test_login_with_invalid_password_shows_error(): void
    {
        $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $csrfToken = $this->authService->getCsrfToken();

        $request = Request::create('/login', 'POST', [
            'email' => 'admin@example.com',
            'password' => 'wrongpassword',
            '_csrf_token' => $csrfToken,
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Invalid email or password', $response->getContent());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function test_login_with_invalid_email_shows_error(): void
    {
        $csrfToken = $this->authService->getCsrfToken();

        $request = Request::create('/login', 'POST', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
            '_csrf_token' => $csrfToken,
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Invalid email or password', $response->getContent());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function test_login_without_csrf_token_shows_error(): void
    {
        $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);

        $request = Request::create('/login', 'POST', [
            'email' => 'admin@example.com',
            'password' => 'password123',
            '_csrf_token' => '',
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Invalid CSRF token', $response->getContent());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function test_login_with_invalid_csrf_token_shows_error(): void
    {
        $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $this->authService->getCsrfToken(); // Generate a valid token

        $request = Request::create('/login', 'POST', [
            'email' => 'admin@example.com',
            'password' => 'password123',
            '_csrf_token' => 'invalid_token',
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Invalid CSRF token', $response->getContent());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function test_logout_clears_session_and_redirects_to_login(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $_SESSION['user_id'] = $user->getId();
        $csrfToken = $this->authService->getCsrfToken();

        $request = Request::create('/logout', 'POST', [
            '_csrf_token' => $csrfToken,
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->headers->get('Location'));
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function test_protected_route_redirects_to_login_when_not_authenticated(): void
    {
        $request = Request::create('/users', 'GET');

        $response = $this->dispatch($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->headers->get('Location'));
    }

    public function test_protected_route_accessible_when_authenticated(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $_SESSION['user_id'] = $user->getId();

        $request = Request::create('/users', 'GET');

        $response = $this->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Users', $response->getContent());
    }

    public function test_public_route_accessible_without_authentication(): void
    {
        $request = Request::create('/login', 'GET');

        $response = $this->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Sign in', $response->getContent());
    }

    public function test_csrf_protection_on_post_requests_without_token(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $_SESSION['user_id'] = $user->getId();

        $request = Request::create('/users', 'POST', [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => 'viewer',
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Invalid CSRF token', $response->getContent());
    }

    public function test_csrf_protection_on_post_requests_with_invalid_token(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $_SESSION['user_id'] = $user->getId();
        $this->authService->getCsrfToken(); // Generate valid token

        $request = Request::create('/users', 'POST', [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => 'viewer',
            '_csrf_token' => 'invalid_token',
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Invalid CSRF token', $response->getContent());
    }

    public function test_csrf_protection_allows_post_with_valid_token(): void
    {
        $user = $this->createAndSaveUser('admin@example.com', 'password123', UserRole::Admin);
        $_SESSION['user_id'] = $user->getId();
        $csrfToken = $this->authService->getCsrfToken();

        $request = Request::create('/users', 'POST', [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => 'viewer',
            '_csrf_token' => $csrfToken,
        ]);

        $response = $this->dispatch($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/users', $response->headers->get('Location'));
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
}
