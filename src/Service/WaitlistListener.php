<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Event\EventListenerInterface;
use App\Model\Event\AppointmentCancelled;

/**
 * Observer: randevu iptal olayını dinler ve serbest kalan slotu bekleme listesine teklif ettirir.
 * Kaydı config/events.php içindedir.
 */
final class WaitlistListener implements EventListenerInterface
{
    public function __construct(private readonly WaitlistService $waitlist)
    {
    }

    public function handle(object $event): void
    {
        if ($event instanceof AppointmentCancelled) {
            $this->waitlist->offerFreedSlot($event->doctorId, $event->startsAt, $event->endsAt);
        }
    }
}
