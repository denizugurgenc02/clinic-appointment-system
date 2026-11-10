<?php

declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Bekleme listesi kaydının durumu.
 *
 * Waiting ──(iptal olayı)──► Offered ──► Accepted
 *                               │──────► Declined (ret)
 *                               │──────► Expired  (30 dk doldu)
 *                               └──────► Waiting  (slot bu arada başkası tarafından alındı)
 */
enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';

    /**
     * Hasta hâlâ sırada mı? (K10: doktor başına en fazla bir açık kayıt)
     */
    public function isOpen(): bool
    {
        return $this === self::Waiting || $this === self::Offered;
    }

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Sırada',
            self::Offered => 'Teklif bekliyor',
            self::Accepted => 'Kabul edildi',
            self::Declined => 'Vazgeçildi',
            self::Expired => 'Süresi doldu',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Waiting => 'info',
            self::Offered => 'warning',
            self::Accepted => 'success',
            self::Declined, self::Expired => 'secondary',
        };
    }
}
