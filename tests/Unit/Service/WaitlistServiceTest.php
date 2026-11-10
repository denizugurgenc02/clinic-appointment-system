<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Model\Doctor;
use App\Model\Enum\AppointmentStatus;
use App\Model\Enum\WaitlistStatus;
use App\Model\Patient;
use App\Repository\Contract\WaitlistRepositoryInterface;
use App\Service\AppointmentService;
use App\Service\Exception\DomainRuleException;
use App\Service\Exception\SlotAlreadyTakenException;
use App\Service\WaitlistService;
use Tests\Support\AppTestCase;

/**
 * Çekirdek problem 3: iptal -> Observer -> bekleme listesindeki sıradaki hastaya süreli teklif.
 * "Şimdi": 16.11.2026 Pazartesi 08:00. Teklif süresi 30 dk.
 */
final class WaitlistServiceTest extends AppTestCase
{
    private AppointmentService $appointments;
    private WaitlistService $waitlist;
    private WaitlistRepositoryInterface $repository;
    private Doctor $doctor;
    private Patient $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appointments = $this->get(AppointmentService::class);
        $this->waitlist = $this->get(WaitlistService::class);
        $this->repository = $this->get(WaitlistRepositoryInterface::class);
        $this->doctor = $this->doctor();
        $this->owner = $this->patient('Randevu Sahibi');
    }

    /**
     * Sahibin 17.11 10:00 randevusunu iptal eder (Observer'ı tetikler).
     */
    private function cancelOwnersAppointment(): void
    {
        $appointment = $this->appointments->book($this->owner->id(), $this->doctor->id(), self::at('2026-11-17 10:00'));
        $this->appointments->cancel($appointment->id(), $this->owner);
    }

    private function entryStatus(int $entryId): WaitlistStatus
    {
        $entry = $this->repository->find($entryId);
        self::assertNotNull($entry);

        return $entry->status;
    }

    public function testCancellationOffersTheSlotToFirstPatientInQueue(): void
    {
        $first = $this->waitlist->join($this->patient('Birinci')->id(), $this->doctor->id(), null);
        $this->clock->advance('PT1M');
        $second = $this->waitlist->join($this->patient('İkinci')->id(), $this->doctor->id(), null);

        $this->cancelOwnersAppointment();

        $offered = $this->repository->find($first->id());
        self::assertSame(WaitlistStatus::Offered, $offered?->status, 'FIFO: ilk giren teklifi alır');
        self::assertSame('2026-11-17 10:00', $offered->offeredStartsAt?->format('Y-m-d H:i'));
        self::assertSame('2026-11-16 08:31', $offered->offerExpiresAt?->format('Y-m-d H:i'), 'Teklif 30 dk geçerli');
        self::assertSame(WaitlistStatus::Waiting, $this->entryStatus($second->id()));
    }

    public function testOfferRespectsDesiredDate(): void
    {
        $wantsOtherDay = $this->waitlist->join($this->patient('Cuma')->id(), $this->doctor->id(), self::at('2026-11-20'));
        $this->clock->advance('PT1M');
        $anyDay = $this->waitlist->join($this->patient('Herhangi')->id(), $this->doctor->id(), null);

        $this->cancelOwnersAppointment();

        self::assertSame(WaitlistStatus::Waiting, $this->entryStatus($wantsOtherDay->id()), 'Tercih ettiği gün değil');
        self::assertSame(WaitlistStatus::Offered, $this->entryStatus($anyDay->id()));
    }

    public function testDeclinedOfferMovesToNextPatient(): void
    {
        $firstPatient = $this->patient('Birinci');
        $first = $this->waitlist->join($firstPatient->id(), $this->doctor->id(), null);
        $this->clock->advance('PT1M');
        $second = $this->waitlist->join($this->patient('İkinci')->id(), $this->doctor->id(), null);
        $this->cancelOwnersAppointment();

        $this->waitlist->decline($first->id(), $firstPatient);

        self::assertSame(WaitlistStatus::Declined, $this->entryStatus($first->id()));
        self::assertSame(WaitlistStatus::Offered, $this->entryStatus($second->id()));
    }

    public function testExpiredOfferMovesToNextPatientAfter30Minutes(): void
    {
        $first = $this->waitlist->join($this->patient('Birinci')->id(), $this->doctor->id(), null);
        $this->clock->advance('PT1M');
        $second = $this->waitlist->join($this->patient('İkinci')->id(), $this->doctor->id(), null);
        $this->cancelOwnersAppointment();

        $this->clock->advance('PT29M');
        self::assertSame(0, $this->waitlist->expireStale(), '29. dakikada teklif hâlâ geçerli');

        $this->clock->advance('PT2M');
        self::assertSame(1, $this->waitlist->expireStale());

        self::assertSame(WaitlistStatus::Expired, $this->entryStatus($first->id()));
        self::assertSame(WaitlistStatus::Offered, $this->entryStatus($second->id()));
    }

    public function testAcceptingOfferCreatesAppointment(): void
    {
        $patient = $this->patient('Bekleyen');
        $entry = $this->waitlist->join($patient->id(), $this->doctor->id(), null);
        $this->cancelOwnersAppointment();

        $appointment = $this->waitlist->accept($entry->id(), $patient);

        self::assertSame(AppointmentStatus::Booked, $appointment->status);
        self::assertSame($patient->id, $appointment->patientId);
        self::assertSame('2026-11-17 10:00', $appointment->startsAt->format('Y-m-d H:i'));
        self::assertSame(WaitlistStatus::Accepted, $this->entryStatus($entry->id()));
    }

    public function testAcceptWhenSlotWasTakenMeanwhileKeepsPatientInQueue(): void
    {
        $patient = $this->patient('Bekleyen');
        $entry = $this->waitlist->join($patient->id(), $this->doctor->id(), null);
        $this->cancelOwnersAppointment();

        // Teklif süresince slot herkese açık: başka biri önce alıyor
        $this->appointments->book($this->patient('Hızlı')->id(), $this->doctor->id(), self::at('2026-11-17 10:00'));

        try {
            $this->waitlist->accept($entry->id(), $patient);
            self::fail('Dolu slot kabul edilmemeli');
        } catch (SlotAlreadyTakenException) {
        }

        self::assertSame(WaitlistStatus::Waiting, $this->entryStatus($entry->id()), 'Hasta sırasını kaybetmez');
    }

    public function testPatientCannotJoinSameDoctorsQueueTwice(): void
    {
        $patient = $this->patient();
        $this->waitlist->join($patient->id(), $this->doctor->id(), null);

        $this->expectException(DomainRuleException::class);

        $this->waitlist->join($patient->id(), $this->doctor->id(), self::at('2026-11-18'));
    }

    public function testNoOfferWhenQueueIsEmpty(): void
    {
        $this->cancelOwnersAppointment();

        self::assertSame([], $this->waitlist->openEntries());
    }
}
