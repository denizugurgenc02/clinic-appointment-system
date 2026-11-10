<?php

declare(strict_types=1);

namespace App\Core\Event;

use Closure;

/**
 * Observer deseninde "özne" (subject). Olayı yayınlayan servis, dinleyicileri
 * tanımaz; yalnızca dispatch() çağırır. Dinleyiciler config/events.php içinde kaydedilir.
 *
 * Dinleyici hazır nesne olarak ya da fabrika (Closure) olarak verilebilir; fabrika
 * olay ilk yayınlandığında çalışır, böylece her istekte tüm servis zinciri kurulmaz.
 */
final class EventDispatcher
{
    /** @var array<class-string, list<EventListenerInterface|Closure(): EventListenerInterface>> */
    private array $listeners = [];

    /**
     * @param class-string                                      $eventClass
     * @param EventListenerInterface|Closure(): EventListenerInterface $listener
     */
    public function listen(string $eventClass, EventListenerInterface|Closure $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Dinleyiciler kayıt sırasıyla, senkron olarak çalışır.
     */
    public function dispatch(object $event): void
    {
        foreach ($this->listenersFor($event::class) as $listener) {
            $listener->handle($event);
        }
    }

    /**
     * @param class-string $eventClass
     * @return list<EventListenerInterface>
     */
    public function listenersFor(string $eventClass): array
    {
        $resolved = [];
        foreach ($this->listeners[$eventClass] ?? [] as $listener) {
            $resolved[] = $listener instanceof Closure ? $listener() : $listener;
        }

        // Üretilen nesneler saklanır; fabrika yalnızca bir kez çalışır
        if ($resolved !== []) {
            $this->listeners[$eventClass] = $resolved;
        }

        return $resolved;
    }
}
