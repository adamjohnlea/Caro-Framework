<?php

declare(strict_types=1);

namespace App\Shared\Container;

use App\Shared\Providers\ServiceProvider;
use RuntimeException;

final class Container
{
    /** @var array<string, callable(): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<int, ServiceProvider> */
    private array $providers = [];

    private bool $booted = false;

    /**
     * @param callable(): mixed $factory
     */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new RuntimeException(sprintf('Service "%s" is not registered in the container.', $id));
        }

        $this->instances[$id] = ($this->factories[$id])();

        return $this->instances[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }

    /**
     * Register a service provider.
     */
    public function registerProvider(ServiceProvider $provider): void
    {
        $this->providers[] = $provider;
        $provider->register();
    }

    /**
     * Boot all registered service providers.
     * This should be called after all providers are registered.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->boot();
        }

        $this->booted = true;
    }
}
