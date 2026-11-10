<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Listeleme ve rapor ekranları için okuma modeli: randevu + hasta + doktor + poliklinik.
 * Aşama 3'te tek bir JOIN sorgusunun satırına karşılık gelir.
 */
final class AppointmentDetails
{
    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $patientName,
        public readonly string $patientEmail,
        public readonly string $patientPhone,
        public readonly string $doctorName,
        public readonly int $departmentId,
        public readonly string $departmentName,
    ) {
    }
}
