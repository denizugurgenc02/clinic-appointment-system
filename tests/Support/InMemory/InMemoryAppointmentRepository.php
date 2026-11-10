<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\Appointment;
use App\Model\AppointmentDetails;
use App\Model\AppointmentFilter;
use App\Model\Doctor;
use App\Model\Patient;
use App\Repository\Contract\AppointmentRepositoryInterface;
use DateTimeImmutable;

final class InMemoryAppointmentRepository implements AppointmentRepositoryInterface
{
    public function __construct(private readonly InMemoryDatabase $db)
    {
    }

    public function find(int $id): ?Appointment
    {
        return isset($this->db->appointments[$id]) ? clone $this->db->appointments[$id] : null;
    }

    public function save(Appointment $appointment): Appointment
    {
        $appointment->id ??= $this->db->nextId('appointments');
        $this->db->appointments[$appointment->id] = clone $appointment;

        return $appointment;
    }

    public function activeForDoctorBetween(int $doctorId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->active(static fn (Appointment $a): bool => $a->doctorId === $doctorId, $from, $to);
    }

    public function activeForPatientBetween(int $patientId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->active(static fn (Appointment $a): bool => $a->patientId === $patientId, $from, $to);
    }

    public function search(AppointmentFilter $filter): array
    {
        $term = mb_strtolower(trim((string) $filter->term));
        $from = $filter->dateFrom?->setTime(0, 0);
        $to = $filter->dateTo?->setTime(0, 0)->modify('+1 day');
        $result = [];

        foreach ($this->db->appointments as $appointment) {
            $patient = $this->db->users[$appointment->patientId] ?? null;
            $doctor = $this->db->users[$appointment->doctorId] ?? null;
            if (!$patient instanceof Patient || !$doctor instanceof Doctor) {
                continue;
            }

            $matches = ($filter->patientId === null || $appointment->patientId === $filter->patientId)
                && ($filter->doctorId === null || $appointment->doctorId === $filter->doctorId)
                && ($filter->departmentId === null || $doctor->departmentId === $filter->departmentId)
                && ($filter->status === null || $appointment->status === $filter->status)
                && ($from === null || $appointment->startsAt >= $from)
                && ($to === null || $appointment->startsAt < $to)
                && ($term === '' || str_contains(mb_strtolower($patient->name . ' ' . $patient->email), $term));

            if ($matches) {
                $result[] = new AppointmentDetails(
                    clone $appointment,
                    $patient->name,
                    $patient->email,
                    $patient->phone,
                    $doctor->displayName(),
                    $doctor->departmentId,
                    $this->db->departments[$doctor->departmentId]->name ?? '',
                );
            }
        }

        usort($result, static fn (AppointmentDetails $a, AppointmentDetails $b): int => $a->appointment->startsAt <=> $b->appointment->startsAt);

        return $result;
    }

    /**
     * @param callable(Appointment): bool $belongs
     * @return list<Appointment>
     */
    private function active(callable $belongs, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $result = [];
        foreach ($this->db->appointments as $appointment) {
            if ($belongs($appointment) && $appointment->status->isActive() && $appointment->overlaps($from, $to)) {
                $result[] = clone $appointment;
            }
        }

        return $result;
    }
}
