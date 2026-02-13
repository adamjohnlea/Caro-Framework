<?php

declare(strict_types=1);

namespace App\Shared\Container;

use App\Shared\Providers\ServiceProvider;
use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    /**
     * @param callable(): mixed $factory
     */
    public function set(string $id, callable $factory): void;

    public function registerProvider(ServiceProvider $provider): void;

    public function boot(): void;

    /** @return list<ServiceProvider> */
    public function getProviders(): array;
}
