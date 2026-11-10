<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Model\Enum\AppointmentStatus;
use App\Service\AppointmentService;
use App\Service\ReportService;
use Tests\Support\AppTestCase;

final class ReportServiceTest extends AppTestCase
{
    public function testDailyReportCountsStatusesAndRates(): void
    {
        // Salı 09:00-11:00, 30 dk -> günlük kapasite 4 slot
        $doctor = $this->doctor(rows: [[2, '09:00', '11:00']]);
        $appointments = $this->get(AppointmentService::class);

        $a = $appointments->book($this->patient('A')->id(), $doctor->id(), self::at('2026-11-17 09:00'));
        $appointments->book($this->patient('B')->id(), $doctor->id(), self::at('2026-11-17 09:30'));
        $c = $appointments->book($this->patient('C')->id(), $doctor->id(), self::at('2026-11-17 10:00'));
        $appointments->cancel($c->id(), $this->get(\App\Repository\Contract\PatientRepositoryInterface::class)->find($c->patientId) ?? self::fail());

        $this->clock->set(self::at('2026-11-17 12:00'));
        $appointments->markOutcome($a->id(), AppointmentStatus::Completed, $doctor);

        $report = $this->get(ReportService::class)->daily(self::at('2026-11-17'));

        self::assertCount(3, $report->rows, 'Liste: randevu + hasta + doktor birleşimi');
        self::assertSame(3, $report->total());
        self::assertSame(1, $report->count(AppointmentStatus::Completed));
        self::assertSame(1, $report->count(AppointmentStatus::Booked));
        self::assertSame(1, $report->count(AppointmentStatus::Cancelled));
        self::assertSame(4, $report->capacity);
        self::assertSame(50.0, $report->occupancyRate(), '2 dolu / 4 slot');
        self::assertSame(33.3, $report->cancellationRate());
    }
}
