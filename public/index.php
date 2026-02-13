<?php

declare(strict_types=1);

/** @var array{app: array{env: string, debug: bool, name: string}, database: array{driver: string, path: string, host: string, port: string, name: string, user: string, password: string}, modules: array{auth: bool, email: bool, queue: bool}, ses: array{region: string, access_key: string, secret_key: string, from_address: string}} $config */
$config = require __DIR__ . '/../src/bootstrap.php';

use App\Database\Database;
use App\Database\DatabaseFactory;
use App\Database\MigrationRunner;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AuthenticationMiddleware;
use App\Http\Middleware\AuthorizationMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Middleware\MiddlewarePipeline;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Router;
use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\Application\Services\UserService;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Infrastructure\Repositories\SqliteUserRepository;
use App\Shared\Container\Container;
use App\Shared\Twig\AssetVersionExtension;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// ── Container ────────────────────────────────────────────────────────

$container = new Container();

$container->set(LoggerInterface::class, static function () use ($config): LoggerInterface {
    $logger = new Logger($config['app']['name']);
    $logger->pushHandler(
        new StreamHandler(
            __DIR__ . '/../storage/logs/app.log',
            $config['app']['debug'] ? Logger::DEBUG : Logger::WARNING,
        ),
    );

    return $logger;
});

$container->set(Database::class, static function () use ($config, $container): Database {
    $database = DatabaseFactory::create($config['database']);

    /** @var LoggerInterface $logger */
    $logger = $container->get(LoggerInterface::class);
    $migrationRunner = new MigrationRunner($database, $logger);
    $migrationRunner->run($config['modules']);

    return $database;
});

$container->set(Environment::class, static function () use ($config): Environment {
    $loader = new FilesystemLoader(__DIR__ . '/../src/Views');
    $twig = new Environment($loader, [
        'strict_variables' => true,
        'cache' => $config['app']['env'] === 'production' ? __DIR__ . '/../storage/cache/twig' : false,
    ]);

    $twig->addGlobal('appName', $config['app']['name']);
    $twig->addExtension(new AssetVersionExtension(__DIR__));

    return $twig;
});

$container->set(HomeController::class, static function () use ($container, $config): HomeController {
    /** @var Environment $twig */
    $twig = $container->get(Environment::class);

    return new HomeController($twig, $config['modules']);
});

$container->set(HealthController::class, static function () use ($container): HealthController {
    /** @var Database $database */
    $database = $container->get(Database::class);

    return new HealthController($database);
});

// ── Routes ───────────────────────────────────────────────────────────

$router = new Router();

$router->get('/', HomeController::class, 'index', 'home');
$router->get('/health', HealthController::class, 'check', 'health');

// ── Middleware ────────────────────────────────────────────────────────

$pipeline = new MiddlewarePipeline();
$pipeline->add(new SecurityHeadersMiddleware());

// ── Auth Module ──────────────────────────────────────────────────────

if ($config['modules']['auth']) {
    session_start();

    $container->set(UserRepositoryInterface::class, static function () use ($container): UserRepositoryInterface {
        /** @var Database $database */
        $database = $container->get(Database::class);

        return new SqliteUserRepository($database);
    });

    $container->set(AuthenticationService::class, static function () use ($container): AuthenticationService {
        /** @var UserRepositoryInterface $userRepository */
        $userRepository = $container->get(UserRepositoryInterface::class);

        return new AuthenticationService($userRepository);
    });

    $container->set(UserService::class, static function () use ($container): UserService {
        /** @var UserRepositoryInterface $userRepository */
        $userRepository = $container->get(UserRepositoryInterface::class);

        return new UserService($userRepository);
    });

    $container->set(AuthController::class, static function () use ($container): AuthController {
        /** @var AuthenticationService $authService */
        $authService = $container->get(AuthenticationService::class);
        /** @var Environment $twig */
        $twig = $container->get(Environment::class);

        return new AuthController($authService, $twig);
    });

    $container->set(UserController::class, static function () use ($container): UserController {
        /** @var UserService $userService */
        $userService = $container->get(UserService::class);
        /** @var AuthenticationService $authService */
        $authService = $container->get(AuthenticationService::class);
        /** @var Environment $twig */
        $twig = $container->get(Environment::class);

        return new UserController($userService, $authService, $twig);
    });

    // Auth routes
    $router->get('/login', AuthController::class, 'showLogin', 'login');
    $router->post('/login', AuthController::class, 'login', 'login.post');
    $router->get('/logout', AuthController::class, 'logout', 'logout');

    // User management routes
    $router->get('/users', UserController::class, 'index', 'users.index');
    $router->get('/users/create', UserController::class, 'create', 'users.create');
    $router->post('/users', UserController::class, 'store', 'users.store');
    $router->get('/users/{id}/edit', UserController::class, 'edit', 'users.edit');
    $router->post('/users/{id}/update', UserController::class, 'update', 'users.update');
    $router->post('/users/{id}/delete', UserController::class, 'destroy', 'users.destroy');

    // Auth middleware
    /** @var AuthenticationService $authService */
    $authService = $container->get(AuthenticationService::class);
    $pipeline->add(new AuthenticationMiddleware($authService));
    $pipeline->add(new CsrfMiddleware($authService));
    $pipeline->add(new AuthorizationMiddleware($authService));

    // Add auth globals to Twig
    /** @var Environment $twig */
    $twig = $container->get(Environment::class);
    $currentUser = $authService->getCurrentUser();
    $twig->addGlobal('currentUser', $currentUser);
    $twig->addGlobal('csrf_token', $authService->getCsrfToken());
    $twig->addGlobal('authEnabled', true);
}

// ── Email Module ─────────────────────────────────────────────────────

if ($config['modules']['email']) {
    $container->set(
        \App\Modules\Email\Application\Services\EmailServiceInterface::class,
        static function () use ($config, $container): \App\Modules\Email\Application\Services\EmailServiceInterface {
            if ($config['ses']['access_key'] !== '' && $config['ses']['secret_key'] !== '') {
                return new \App\Modules\Email\Infrastructure\Services\SesEmailService($config['ses']);
            }

            /** @var LoggerInterface $logger */
            $logger = $container->get(LoggerInterface::class);

            return new \App\Modules\Email\Infrastructure\Services\LogEmailService($logger);
        },
    );
}

// ── Queue Module ─────────────────────────────────────────────────────

if ($config['modules']['queue']) {
    $container->set(
        \App\Modules\Queue\Domain\Repositories\QueueRepositoryInterface::class,
        static function () use ($container): \App\Modules\Queue\Domain\Repositories\QueueRepositoryInterface {
            /** @var Database $database */
            $database = $container->get(Database::class);

            return new \App\Modules\Queue\Infrastructure\Repositories\SqliteQueueRepository($database);
        },
    );

    $container->set(
        \App\Modules\Queue\Application\Services\QueueService::class,
        static function () use ($container): \App\Modules\Queue\Application\Services\QueueService {
            /** @var \App\Modules\Queue\Domain\Repositories\QueueRepositoryInterface $queueRepository */
            $queueRepository = $container->get(\App\Modules\Queue\Domain\Repositories\QueueRepositoryInterface::class);
            /** @var LoggerInterface $logger */
            $logger = $container->get(LoggerInterface::class);

            return new \App\Modules\Queue\Application\Services\QueueService($queueRepository, $logger);
        },
    );
}

// ── Dispatch ─────────────────────────────────────────────────────────

$request = Request::createFromGlobals();

/** @var LoggerInterface $logger */
$logger = $container->get(LoggerInterface::class);

try {
    $response = $pipeline->handle(
        $request,
        static function (Request $request) use ($router, $container): Response {
            return $router->dispatch(
                $request,
                static function (array $parameters, Request $request) use ($container): Response {
                    $controllerClass = $parameters['_controller'];
                    $method = $parameters['_method'];
                    $id = isset($parameters['id']) ? (int) $parameters['id'] : null;

                    /** @var object $controller */
                    $controller = $container->get($controllerClass);

                    if ($id !== null) {
                        if ($method === 'update' || $method === 'destroy') {
                            /** @var Response */
                            return $controller->{$method}($id, $request);
                        }

                        /** @var Response */
                        return $controller->{$method}($id);
                    }

                    if ($method === 'login' || $method === 'store') {
                        /** @var Response */
                        return $controller->{$method}($request);
                    }

                    /** @var Response */
                    return $controller->{$method}();
                },
            );
        },
    );
} catch (\Throwable $e) {
    $logger->error('Unhandled exception: ' . $e->getMessage(), [
        'exception' => $e::class,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    /** @var Environment $twig */
    $twig = $container->get(Environment::class);

    if ($config['app']['debug']) {
        $response = new Response(
            $twig->render('errors/500.twig', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]),
            500,
        );
    } else {
        $response = new Response(
            $twig->render('errors/500.twig'),
            500,
        );
    }
}

$response->send();
