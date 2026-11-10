<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\AppointmentService;
use App\Service\Exception\DomainRuleException;
use App\Service\Exception\ValidationException;
use App\Service\ScheduleService;
use Tests\Support\AppTestCase;

final class ScheduleServiceTest extends AppTestCase
{
    private ScheduleService $schedules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schedules = $this->get(ScheduleService::class);
    }

    public function testOverlappingRowOnTheSameDayIsRejected(): void
    {
        $doctor = $this->doctor(rows: [[1, '09:00', '12:00']]);

        // Farklı gün ve öğleden sonra satırı sorun değil
        $this->schedules->addRow($doctor->id(), 2, '09:00', '12:00');
        $this->schedules->addRow($doctor->id(), 1, '13:00', '17:00');

        $this->expectException(DomainRuleException::class);
        $this->expectExceptionMessage('çakışıyor');

        $this->schedules->addRow($doctor->id(), 1, '11:30', '13:30');
    }

    public function testInvalidTimesAreReportedPerField(): void
    {
        $doctor = $this->doctor(rows: [[1, '09:00', '12:00']]);

        try {
            $this->schedules->addRow($doctor->id(), 8, '25:00', '10:00');
            self::fail('Doğrulama hatası bekleniyordu');
        } catch (ValidationException $e) {
            self::assertEqualsCanonicalizing(['day_of_week', 'start_time'], array_keys($e->errors()));
        }
    }

    public function testAddingTimeOffReportsConflictingAppointments(): void
    {
        $doctor = $this->doctor();
        $this->get(AppointmentService::class)->book($this->patient()->id(), $doctor->id(), self::at('2026-11-18 10:00'));

        $result = $this->schedules->addTimeOff($doctor->id(), self::at('2026-11-18 00:00'), self::at('2026-11-19 00:00'), 'İzin');

        self::assertCount(1, $result['conflicts'], 'Sekreter, izne denk gelen randevu için uyarılmalı');
    }
}
