<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * İzin veya istisna aralığı (K4). Tam gün izin: 00:00 - ertesi gün 00:00;
 * gün içi istisna: ör. 14:00 - 16:00.
 */
final class TimeOff
{
    public function __construct(
        public ?int $id,
        public int $doctorId,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $reason = '',
    ) {
    }

    /**
     * [start, end) aralığı izinle kesişiyor mu?
     */
    public function overlaps(DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        return $start < $this->endsAt && $end > $this->startsAt;
    }
}
