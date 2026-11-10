<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Bekleme listesi ekranları için okuma modeli (kayıt + hasta + doktor + poliklinik).
 */
final class WaitlistDetails
{
    public function __construct(
        public readonly WaitlistEntry $entry,
        public readonly string $patientName,
        public readonly string $doctorName,
        public readonly string $departmentName,
        /** Doktorun bekleme sırasındaki konumu (1 = sıradaki); kapalı kayıtlarda null */
        public readonly ?int $position = null,
    ) {
    }
}
