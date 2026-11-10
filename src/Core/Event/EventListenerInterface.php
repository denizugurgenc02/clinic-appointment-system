<?php

declare(strict_types=1);

namespace App\Core\Event;

/**
 * Observer deseninde "gözlemci". Bir olay yayınlandığında handle() çağrılır.
 * Örnek: WaitlistListener, AppointmentCancelled olayında sıradaki hastaya teklif verir.
 */
interface EventListenerInterface
{
    public function handle(object $event): void;
}
