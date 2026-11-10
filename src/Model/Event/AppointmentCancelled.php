<?php

declare(strict_types=1);

namespace App\Model\Event;

use DateTimeImmutable;

/**
 * Bir randevu iptal edildiğinde (veya tarihi değiştirildiğinde eski slot için)
 * yayınlanan olay. WaitlistListener bunu dinler (Observer).
 */
final class AppointmentCancelled
{
    public function __construct(
        public readonly int $appointmentId,
        public readonly int $doctorId,
        public readonly DateTimeImmutable $startsAt,
        public readonly DateTimeImmutable $endsAt,
    ) {
    }
}
