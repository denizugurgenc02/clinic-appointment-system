<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Event\EventDispatcher;
use PHPUnit\Framework\TestCase;
use Tests\Support\Stub\OtherEvent;
use Tests\Support\Stub\RecordingListener;
use Tests\Support\Stub\SomethingHappened;

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
