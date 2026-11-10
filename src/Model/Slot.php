<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\SlotStatus;
use DateTimeImmutable;

/**
 * Değer nesnesi: çalışma takviminden üretilen randevu aralığı. Kaydedilmez.
 */
final class Slot
{
    public function __construct(
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end,
        public readonly SlotStatus $status,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->status === SlotStatus::Available;
    }

    /**
     * @return array{start: string, end: string, time: string, status: string}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start->format('Y-m-d\TH:i'),
            'end' => $this->end->format('Y-m-d\TH:i'),
            'time' => $this->start->format('H:i'),
            'status' => $this->status->value,
        ];
    }
}
