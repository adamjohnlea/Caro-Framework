<?php

declare(strict_types=1);

/** @var array{app: array{env: string, debug: bool, name: string}, database: array{driver: string, path: string, host: string, port: string, name: string, user: string, password: string}, modules: array{auth: bool, email: bool, queue: bool}, ses: array{region: string, access_key: string, secret_key: string, from_address: string}} $config */
$config = require __DIR__ . '/../src/bootstrap.php';

use App\Database\Database;
use App\Database\DatabaseFactory;
use App\Database\MigrationRunner;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\ControllerDispatcher;
use App\Http\Middleware\AuthenticationMiddleware;
use App\Http\Middleware\AuthorizationMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Middleware\MiddlewarePipeline;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Router;
use App\Modules\Auth\Application\Services\AuthenticationService;
use App\Modules\Auth\AuthServiceProvider;
use App\Modules\Email\EmailServiceProvider;
use App\Modules\Queue\QueueServiceProvider;
use App\Shared\Container\Container;
use App\Shared\Events\EventDispatcher;
use App\Shared\Events\EventDispatcherInterface;
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

// ── Core Services ─────────────────────────────────────────────────────

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

$container->set(EventDispatcherInterface::class, static fn (): EventDispatcherInterface => new EventDispatcher());

// ── Module Service Providers ──────────────────────────────────────────

if ($config['modules']['auth']) {
    session_start();
    $container->registerProvider(new AuthServiceProvider($container, $config));
}

if ($config['modules']['email']) {
    $container->registerProvider(new EmailServiceProvider($container, $config));
}

if ($config['modules']['queue']) {
    $container->registerProvider(new QueueServiceProvider($container, $config));
}

// Boot all providers (runs boot() methods)
$container->boot();

// ── Routes ───────────────────────────────────────────────────────────

$router = new Router();

// Core routes
$router->get('/', HomeController::class, 'index', 'home');
$router->get('/health', HealthController::class, 'check', 'health');

// Load module routes
if ($config['modules']['auth']) {
    $authRoutes = require __DIR__ . '/../src/Modules/Auth/routes.php';
    $authRoutes($router);
}

// ── Middleware ────────────────────────────────────────────────────────

$pipeline = new MiddlewarePipeline();
$pipeline->add(new SecurityHeadersMiddleware());

if ($config['modules']['auth']) {
    /** @var AuthenticationService $authService */
    $authService = $container->get(AuthenticationService::class);
    $pipeline->add(new AuthenticationMiddleware($authService));
    $pipeline->add(new CsrfMiddleware($authService));
    $pipeline->add(new AuthorizationMiddleware($authService));
}

// ── Dispatch ─────────────────────────────────────────────────────────

$request = Request::createFromGlobals();

/** @var LoggerInterface $logger */
$logger = $container->get(LoggerInterface::class);

$dispatcher = new ControllerDispatcher($container);

try {
    $response = $pipeline->handle(
        $request,
        static function (Request $request) use ($router, $dispatcher): Response {
            return $router->dispatch(
                $request,
                static fn (array $parameters, Request $request): Response => $dispatcher->dispatch($parameters, $request),
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
