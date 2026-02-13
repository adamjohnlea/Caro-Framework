<?php

declare(strict_types=1);

namespace App\Shared\Events;

interface EventDispatcherInterface
{
    public function dispatch(object $event): object;

    public function listen(string $eventClass, callable $listener): void;
}
