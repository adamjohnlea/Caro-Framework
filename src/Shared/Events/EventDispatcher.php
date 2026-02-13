<?php

declare(strict_types=1);

namespace App\Shared\Events;

use Override;

final class EventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    #[Override]
    public function dispatch(object $event): object
    {
        $eventClass = $event::class;

        foreach ($this->listeners[$eventClass] ?? [] as $listener) {
            $listener($event);
        }

        return $event;
    }

    #[Override]
    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }
}
