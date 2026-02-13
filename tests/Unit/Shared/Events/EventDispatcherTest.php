<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Events;

use App\Shared\Events\EventDispatcher;
use App\Shared\Events\EventDispatcherInterface;
use PHPUnit\Framework\TestCase;

final class EventDispatcherTest extends TestCase
{
    public function test_implements_interface(): void
    {
        $dispatcher = new EventDispatcher();

        $this->assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
    }

    public function test_dispatch_with_no_listeners(): void
    {
        $dispatcher = new EventDispatcher();
        $event = new TestEvent('hello');

        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
    }

    public function test_dispatch_with_one_listener(): void
    {
        $dispatcher = new EventDispatcher();
        $called = false;

        $dispatcher->listen(TestEvent::class, static function (object $event) use (&$called): void {
            $called = true;
        });

        $dispatcher->dispatch(new TestEvent('test'));

        $this->assertTrue($called);
    }

    public function test_dispatch_with_multiple_listeners(): void
    {
        $dispatcher = new EventDispatcher();
        $calls = [];

        $dispatcher->listen(TestEvent::class, static function () use (&$calls): void {
            $calls[] = 'first';
        });
        $dispatcher->listen(TestEvent::class, static function () use (&$calls): void {
            $calls[] = 'second';
        });

        $dispatcher->dispatch(new TestEvent('test'));

        $this->assertSame(['first', 'second'], $calls);
    }

    public function test_listeners_only_fire_for_matching_class(): void
    {
        $dispatcher = new EventDispatcher();
        $called = false;

        $dispatcher->listen(OtherEvent::class, static function () use (&$called): void {
            $called = true;
        });

        $dispatcher->dispatch(new TestEvent('test'));

        $this->assertFalse($called);
    }

    public function test_event_object_returned(): void
    {
        $dispatcher = new EventDispatcher();
        $event = new TestEvent('data');

        $dispatcher->listen(TestEvent::class, static function (): void {
            // listener doesn't modify event
        });

        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertSame('data', $result->message);
    }
}

final readonly class TestEvent
{
    public function __construct(
        public string $message,
    ) {
    }
}

final readonly class OtherEvent
{
    public function __construct(
        public string $data,
    ) {
    }
}
