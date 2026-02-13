<?php

declare(strict_types=1);

namespace App\Shared\Cli;

use App\Database\Database;
use App\Database\DatabaseFactory;
use App\Database\MigrationRunner;
use App\Modules\Auth\AuthServiceProvider;
use App\Modules\Email\EmailServiceProvider;
use App\Modules\Queue\QueueServiceProvider;
use App\Shared\Container\Container;
use App\Shared\Container\ContainerInterface;
use App\Shared\Events\EventDispatcher;
use App\Shared\Events\EventDispatcherInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class CliBootstrap
{
    /**
     * @param array{app: array{env: string, debug: bool, name: string}, database: array{driver: string, path: string, host: string, port: string, name: string, user: string, password: string}, modules: array{auth: bool, email: bool, queue: bool}, ses: array{region: string, access_key: string, secret_key: string, from_address: string}} $config
     * @param list<HandlerInterface>                                                                                                                                                                                                                                                                                                             $logHandlers
     */
    public static function createContainer(array $config, array $logHandlers = []): ContainerInterface
    {
        $container = new Container();

        $container->set(LoggerInterface::class, static function () use ($config, $logHandlers): LoggerInterface {
            $logger = new Logger($config['app']['name']);

            if ($logHandlers === []) {
                $logger->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));
            } else {
                foreach ($logHandlers as $handler) {
                    $logger->pushHandler($handler);
                }
            }

            return $logger;
        });

        $container->set(EventDispatcherInterface::class, static fn (): EventDispatcherInterface => new EventDispatcher());

        $container->set(Database::class, static function () use ($config, $container): Database {
            $database = DatabaseFactory::create($config['database']);

            /** @var LoggerInterface $logger */
            $logger = $container->get(LoggerInterface::class);
            $migrationRunner = new MigrationRunner($database, $logger);
            $migrationRunner->run($config['modules']);

            return $database;
        });

        if ($config['modules']['auth']) {
            $container->registerProvider(new AuthServiceProvider($container, $config));
        }

        if ($config['modules']['email']) {
            $container->registerProvider(new EmailServiceProvider($container, $config));
        }

        if ($config['modules']['queue']) {
            $container->registerProvider(new QueueServiceProvider($container, $config));
        }

        // CLI does not call boot() — boot() is for web-specific setup (e.g., Twig globals)

        return $container;
    }
}
