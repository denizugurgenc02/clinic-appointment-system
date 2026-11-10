<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\AppointmentStatus;
use DateTimeImmutable;

/**
 * Günlük randevu raporu: liste + durum bazında toplamlar + oranlar.
 */
final class DailyReport
{
    /**
     * @param list<AppointmentDetails>                                                  $rows
     * @param array<string, int>                                                        $countsByStatus AppointmentStatus değeri => adet
     * @param list<array{department: string, appointments: int, capacity: int}>         $byDepartment
     */
    public function __construct(
        public readonly DateTimeImmutable $date,
        public readonly array $rows,
        public readonly array $countsByStatus,
        public readonly int $capacity,
        public readonly array $byDepartment,
    ) {
    }

    public function count(AppointmentStatus $status): int
    {
        return $this->countsByStatus[$status->value] ?? 0;
    }

    public function total(): int
    {
        return array_sum($this->countsByStatus);
    }

    /**
     * Slotu dolduran randevular (iptaller hariç).
     */
    public function occupied(): int
    {
        return $this->total() - $this->count(AppointmentStatus::Cancelled);
    }

    public function cancellationRate(): float
    {
        return self::ratio($this->count(AppointmentStatus::Cancelled), $this->total());
    }

    public function occupancyRate(): float
    {
        return self::ratio($this->occupied(), $this->capacity);
    }

    /**
     * Sonuçlanmış randevular içinde gelmeyenlerin oranı.
     */
    public function noShowRate(): float
    {
        $concluded = $this->count(AppointmentStatus::Completed) + $this->count(AppointmentStatus::NoShow);

        return self::ratio($this->count(AppointmentStatus::NoShow), $concluded);
    }

    private static function ratio(int $part, int $whole): float
    {
        return $whole === 0 ? 0.0 : round($part / $whole * 100, 1);
    }
}
