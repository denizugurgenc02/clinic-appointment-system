<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Model\Appointment;
use App\Model\AppointmentFilter;
use App\Repository\Contract\AppointmentRepositoryInterface;
use DateTimeImmutable;

/**
 * Eşzamanlılık testi için: kayıttan önce bekleyerek "kontrol" ile "kayıt" arasındaki
 * yarış penceresini genişletir. Kilit olmasaydı iki süreç de slotu boş görüp kaydederdi.
 */
final class SlowSaveAppointmentRepository implements AppointmentRepositoryInterface
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $inner,
        private readonly int $delayMicroseconds,
    ) {
    }

    public function save(Appointment $appointment): Appointment
    {
        usleep($this->delayMicroseconds);

        return $this->inner->save($appointment);
    }

    public function find(int $id): ?Appointment
    {
        return $this->inner->find($id);
    }

    public function activeForDoctorBetween(int $doctorId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->inner->activeForDoctorBetween($doctorId, $from, $to);
    }

    public function activeForPatientBetween(int $patientId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->inner->activeForPatientBetween($patientId, $from, $to);
    }

    public function search(AppointmentFilter $filter): array
    {
        return $this->inner->search($filter);
    }
}
