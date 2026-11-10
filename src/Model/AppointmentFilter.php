<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\AppointmentStatus;
use DateTimeImmutable;

/**
 * Randevu arama/filtreleme ölçütleri. Boş (null) alanlar filtrelenmez.
 */
final class AppointmentFilter
{
    public function __construct(
        /** Hasta adı veya e-postasında geçen metin */
        public readonly ?string $term = null,
        public readonly ?int $patientId = null,
        public readonly ?int $doctorId = null,
        public readonly ?int $departmentId = null,
        public readonly ?AppointmentStatus $status = null,
        /** Dahil, gün başı */
        public readonly ?DateTimeImmutable $dateFrom = null,
        /** Dahil, gün sonu */
        public readonly ?DateTimeImmutable $dateTo = null,
    ) {
    }
}
