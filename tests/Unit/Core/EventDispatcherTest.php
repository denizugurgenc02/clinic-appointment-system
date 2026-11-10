<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Event\EventDispatcher;
use App\Core\Event\EventListenerInterface;
use PHPUnit\Framework\TestCase;

final class SomethingHappened
{
    public function __construct(public readonly int $id)
    {
    }
}

final class OtherEvent
{
}

final class RecordingListener implements EventListenerInterface
{
    /** @var list<object> */
    public array $received = [];

    public function handle(object $event): void
    {
        $this->received[] = $event;
    }
}

final class EventDispatcherTest extends TestCase
{
    public function testEveryListenerOfTheEventIsNotified(): void
    {
        $dispatcher = new EventDispatcher();
        $first = new RecordingListener();
        $second = new RecordingListener();
        $dispatcher->listen(SomethingHappened::class, $first);
        $dispatcher->listen(SomethingHappened::class, $second);

        $event = new SomethingHappened(5);
        $dispatcher->dispatch($event);

        self::assertSame([$event], $first->received);
        self::assertSame([$event], $second->received);
    }

    public function testListenersOfOtherEventsAreNotNotified(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new RecordingListener();
        $dispatcher->listen(OtherEvent::class, $listener);

        $dispatcher->dispatch(new SomethingHappened(1));

        self::assertSame([], $listener->received);
    }
}
