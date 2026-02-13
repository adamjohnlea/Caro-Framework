<?php

declare(strict_types=1);

namespace App\Shared\Container;

use App\Shared\Providers\ServiceProvider;
use Override;
use RuntimeException;

final class Container implements ContainerInterface
{
    /** @var array<string, callable(): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var list<ServiceProvider> */
    private array $providers = [];

    private bool $booted = false;

    /**
     * @param callable(): mixed $factory
     */
    #[Override]
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    #[Override]
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

    #[Override]
    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }

    #[Override]
    public function registerProvider(ServiceProvider $provider): void
    {
        $this->providers[] = $provider;
        $provider->register();
    }

    #[Override]
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

    /** @return list<ServiceProvider> */
    #[Override]
    public function getProviders(): array
    {
        return $this->providers;
    }
}
