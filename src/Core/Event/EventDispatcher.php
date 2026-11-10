<?php

declare(strict_types=1);

namespace App\Core\Event;

/**
 * Observer deseninde "özne" (subject). Olayı yayınlayan servis, dinleyicileri
 * tanımaz; yalnızca dispatch() çağırır. Dinleyiciler config/events.php içinde kaydedilir.
 */
final class EventDispatcher
{
    /** @var array<class-string, list<EventListenerInterface>> */
    private array $listeners = [];

    /**
     * @param class-string $eventClass
     */
    public function listen(string $eventClass, EventListenerInterface $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Dinleyiciler kayıt sırasıyla, senkron olarak çalışır.
     */
    public function dispatch(object $event): void
    {
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $listener->handle($event);
        }
    }

    /**
     * @param class-string $eventClass
     * @return list<EventListenerInterface>
     */
    public function listenersFor(string $eventClass): array
    {
        return $this->listeners[$eventClass] ?? [];
    }
}
