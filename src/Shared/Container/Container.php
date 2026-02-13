<?php

declare(strict_types=1);

namespace App\Shared\Container;

use RuntimeException;

final class Container
{
    /** @var array<string, callable(): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

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
}
