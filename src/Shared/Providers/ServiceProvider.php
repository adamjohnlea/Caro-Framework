<?php

declare(strict_types=1);

namespace App\Shared\Providers;

use App\Shared\Container\ContainerInterface;

abstract class ServiceProvider
{
    /**
     * @param array{app: array{env: string, debug: bool, name: string}, database: array{driver: string, path: string, host: string, port: string, name: string, user: string, password: string}, modules: array{auth: bool, email: bool, queue: bool}, ses: array{region: string, access_key: string, secret_key: string, from_address: string}} $config
     */
    public function __construct(
        protected ContainerInterface $container,
        protected array $config,
    ) {
    }

    /**
     * Register services into the container.
     */
    abstract public function register(): void;

    /**
     * Boot method called after all providers are registered.
     * Override this method if you need to perform actions after registration.
     */
    public function boot(): void
    {
        // Default: do nothing
    }
}
