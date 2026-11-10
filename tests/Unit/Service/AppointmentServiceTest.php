<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Model\Enum\AppointmentStatus;
use App\Model\TimeOff;
use App\Model\User;
use App\Model\Enum\UserRole;
use App\Repository\Contract\AppointmentRepositoryInterface;
use App\Repository\Contract\TimeOffRepositoryInterface;
use App\Service\AppointmentService;
use App\Service\Exception\AccessDeniedException;
use App\Service\Exception\DomainRuleException;
use App\Service\Exception\SlotAlreadyTakenException;
use DomainException;
use Tests\Support\AppTestCase;

/**
 * Çekirdek problem 2: çakışma önleme ve randevu iş kuralları.
 * "Şimdi": 16.11.2026 Pazartesi 08:00.
 */
final class AppointmentServiceTest extends AppTestCase
{
    private AppointmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->get(AppointmentService::class);
    }

    public function testPatientCanBookAnAvailableSlot(): void
    {
        $doctor = $this->doctor();
        $patient = $this->patient();

        $appointment = $this->service->book($patient->id(), $doctor->id(), self::at('2026-11-17 10:00'), '  Baş ağrısı  ');

        self::assertNotNull($appointment->id);
        self::assertSame(AppointmentStatus::Booked, $appointment->status);
        self::assertSame('2026-11-17 10:30', $appointment->endsAt->format('Y-m-d H:i'), 'Bitiş = başlangıç + slot süresi');
        self::assertSame('Baş ağrısı', $appointment->patientNote);
    }

    public function testSameSlotCannotBeBookedTwice(): void
    {
        $doctor = $this->doctor();
        $this->service->book($this->patient('Ali')->id(), $doctor->id(), self::at('2026-11-17 10:00'));

        $this->expectException(SlotAlreadyTakenException::class);

        $this->service->book($this->patient('Veli')->id(), $doctor->id(), self::at('2026-11-17 10:00'));
    }

    public function testCannotBookDuringLunchBreakOrOutsideSchedule(): void
    {
        $doctor = $this->doctor();
        $patient = $this->patient();

        foreach (['2026-11-17 12:00', '2026-11-17 10:15', '2026-11-21 10:00'] as $invalid) {
            try {
                $this->service->book($patient->id(), $doctor->id(), self::at($invalid));
                self::fail($invalid . ' için randevu verilmemeliydi');
            } catch (DomainRuleException $e) {
                self::assertNotInstanceOf(SlotAlreadyTakenException::class, $e);
            }
        }
    }

    public function testCannotBookOnDoctorsDayOff(): void
    {
        $doctor = $this->doctor();
        $this->get(TimeOffRepositoryInterface::class)->add(
            new TimeOff(null, $doctor->id(), self::at('2026-11-18 00:00'), self::at('2026-11-19 00:00'), 'İzin'),
        );

        $this->expectException(DomainRuleException::class);
        $this->expectExceptionMessage('izinli');

        $this->service->book($this->patient()->id(), $doctor->id(), self::at('2026-11-18 10:00'));
    }

    public function testCannotBookPastSlot(): void
    {
        $doctor = $this->doctor();
        $this->clock->set(self::at('2026-11-17 10:05'));

        $this->expectException(DomainRuleException::class);
        $this->expectExceptionMessage('Geçmiş');

        $this->service->book($this->patient()->id(), $doctor->id(), self::at('2026-11-17 10:00'));
    }

    public function testPatientCannotHaveTwoAppointmentsAtTheSameTime(): void
    {
        $patient = $this->patient();
        $this->service->book($patient->id(), $this->doctor('Dr. A')->id(), self::at('2026-11-17 10:00'));

        $this->expectException(DomainRuleException::class);

        $this->service->book($patient->id(), $this->doctor('Dr. B')->id(), self::at('2026-11-17 10:00'));
    }

    public function testPatientCannotCancelSomeoneElsesAppointment(): void
    {
        $doctor = $this->doctor();
        $owner = $this->patient('Sahip');
        $other = $this->patient('Başkası');
        $appointment = $this->service->book($owner->id(), $doctor->id(), self::at('2026-11-17 10:00'));

        $this->expectException(AccessDeniedException::class);

        $this->service->cancel($appointment->id(), $other);
    }

    public function testCancelFreesTheSlotAndSecretaryCanCancel(): void
    {
        $doctor = $this->doctor();
        $appointment = $this->service->book($this->patient()->id(), $doctor->id(), self::at('2026-11-17 10:00'));
        $secretary = new User(999, 'Sekreter', 's@klinik.test', 'x', UserRole::Secretary);

        $this->service->cancel($appointment->id(), $secretary);

        $again = $this->service->book($this->patient('Yeni')->id(), $doctor->id(), self::at('2026-11-17 10:00'));
        self::assertSame(AppointmentStatus::Booked, $again->status, 'İptal edilen slot tekrar alınabilmeli');
    }

    public function testRescheduleBooksNewSlotAndCancelsOldOne(): void
    {
        $doctor = $this->doctor();
        $patient = $this->patient();
        $old = $this->service->book($patient->id(), $doctor->id(), self::at('2026-11-17 10:00'));

        $new = $this->service->reschedule($old->id(), self::at('2026-11-17 10:30'), $patient);

        $repository = $this->get(AppointmentRepositoryInterface::class);
        self::assertSame(AppointmentStatus::Cancelled, $repository->find($old->id())?->status);
        self::assertSame(AppointmentStatus::Booked, $repository->find($new->id())?->status);
        self::assertSame('10:30', $new->startsAt->format('H:i'));
    }

    public function testFailedRescheduleKeepsOriginalAppointment(): void
    {
        $doctor = $this->doctor();
        $patient = $this->patient();
        $old = $this->service->book($patient->id(), $doctor->id(), self::at('2026-11-17 10:00'));
        $this->service->book($this->patient('Diğer')->id(), $doctor->id(), self::at('2026-11-17 11:00'));

        try {
            $this->service->reschedule($old->id(), self::at('2026-11-17 11:00'), $patient);
            self::fail('Dolu slota taşınmamalı');
        } catch (SlotAlreadyTakenException) {
        }

        self::assertSame(AppointmentStatus::Booked, $this->get(AppointmentRepositoryInterface::class)->find($old->id())?->status);
    }

    public function testDoctorCanMarkOutcomeOnlyAfterAppointmentStarts(): void
    {
        $doctor = $this->doctor();
        $appointment = $this->service->book($this->patient()->id(), $doctor->id(), self::at('2026-11-17 10:00'));

        try {
            $this->service->markOutcome($appointment->id(), AppointmentStatus::Completed, $doctor);
            self::fail('Başlamamış randevu işaretlenmemeli');
        } catch (DomainException) {
        }

        $this->clock->set(self::at('2026-11-17 10:40'));
        $marked = $this->service->markOutcome($appointment->id(), AppointmentStatus::NoShow, $doctor);

        self::assertSame(AppointmentStatus::NoShow, $marked->status);
    }

    public function testDoctorCannotMarkAnotherDoctorsAppointment(): void
    {
        $appointment = $this->service->book($this->patient()->id(), $this->doctor('Dr. A')->id(), self::at('2026-11-17 10:00'));
        $this->clock->set(self::at('2026-11-17 11:00'));

        $this->expectException(AccessDeniedException::class);

        $this->service->markOutcome($appointment->id(), AppointmentStatus::Completed, $this->doctor('Dr. B'));
    }
}
