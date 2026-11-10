<?php

declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Randevu durumu (PHP 8.1 backed enum). Veri katmanında string değeriyle saklanır.
 *
 * Booked ──(hasta/sekreter, başlamadan önce)──► Cancelled
 * Booked ──(doktor, başladıktan sonra)───────► Completed | NoShow
 */
enum AppointmentStatus: string
{
    case Booked = 'booked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * Slotu dolu tutan tek durum Booked'tır (K6).
     */
    public function isActive(): bool
    {
        return $this === self::Booked;
    }

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Aktif',
            self::Completed => 'Tamamlandı',
            self::Cancelled => 'İptal edildi',
            self::NoShow => 'Gelmedi',
        };
    }

    /**
     * Bootstrap rozet rengi.
     */
    public function color(): string
    {
        return match ($this) {
            self::Booked => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'secondary',
            self::NoShow => 'danger',
        };
    }
}
