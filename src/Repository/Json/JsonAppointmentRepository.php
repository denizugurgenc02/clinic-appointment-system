<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Model\Appointment;
use App\Model\AppointmentDetails;
use App\Model\AppointmentFilter;
use App\Model\Enum\AppointmentStatus;
use App\Repository\Contract\AppointmentRepositoryInterface;
use DateTimeImmutable;

final class JsonAppointmentRepository implements AppointmentRepositoryInterface
{
    private const TABLE = 'appointments';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function find(int $id): ?Appointment
    {
        $row = $this->store->find(self::TABLE, $id);

        return $row === null ? null : self::hydrate($row);
    }

    public function save(Appointment $appointment): Appointment
    {
        $row = [
            'patient_id' => $appointment->patientId,
            'doctor_id' => $appointment->doctorId,
            'starts_at' => Row::formatDate($appointment->startsAt),
            'ends_at' => Row::formatDate($appointment->endsAt),
            'status' => $appointment->status->value,
            'patient_note' => $appointment->patientNote,
            'created_at' => Row::formatDate($appointment->createdAt),
            'cancelled_at' => Row::formatDate($appointment->cancelledAt),
        ];

        if ($appointment->id === null) {
            $appointment->id = $this->store->insert(self::TABLE, $row);
        } else {
            $this->store->update(self::TABLE, $appointment->id, $row);
        }

        return $appointment;
    }

    public function activeForDoctorBetween(int $doctorId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->activeWhere('doctor_id', $doctorId, $from, $to);
    }

    public function activeForPatientBetween(int $patientId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->activeWhere('patient_id', $patientId, $from, $to);
    }

    public function search(AppointmentFilter $filter): array
    {
        // appointments ⋈ users(hasta) ⋈ patients ⋈ users(doktor) ⋈ doctors ⋈ departments
        $users = Row::indexBy($this->store->rows('users'));
        $patients = Row::indexBy($this->store->rows('patients'), 'user_id');
        $doctors = Row::indexBy($this->store->rows('doctors'), 'user_id');
        $departments = Row::indexBy($this->store->rows('departments'));
        $term = mb_strtolower(trim((string) $filter->term));
        $from = $filter->dateFrom?->setTime(0, 0);
        $to = $filter->dateTo?->setTime(0, 0)->modify('+1 day');

        $result = [];

        foreach ($this->store->rows(self::TABLE) as $row) {
            $appointment = self::hydrate($row);
            $patientUser = $users[$appointment->patientId] ?? null;
            $doctorUser = $users[$appointment->doctorId] ?? null;
            $doctor = $doctors[$appointment->doctorId] ?? null;

            if ($patientUser === null || $doctorUser === null || $doctor === null) {
                continue;
            }

            $departmentId = Row::int($doctor, 'department_id');
            $patientName = Row::string($patientUser, 'name');
            $patientEmail = Row::string($patientUser, 'email');

            $matches = ($filter->patientId === null || $appointment->patientId === $filter->patientId)
                && ($filter->doctorId === null || $appointment->doctorId === $filter->doctorId)
                && ($filter->departmentId === null || $departmentId === $filter->departmentId)
                && ($filter->status === null || $appointment->status === $filter->status)
                && ($from === null || $appointment->startsAt >= $from)
                && ($to === null || $appointment->startsAt < $to)
                && ($term === '' || str_contains(mb_strtolower($patientName . ' ' . $patientEmail), $term));

            if (!$matches) {
                continue;
            }

            $result[] = new AppointmentDetails(
                $appointment,
                $patientName,
                $patientEmail,
                isset($patients[$appointment->patientId]) ? Row::string($patients[$appointment->patientId], 'phone') : '',
                trim(Row::string($doctor, 'title') . ' ' . Row::string($doctorUser, 'name')),
                $departmentId,
                isset($departments[$departmentId]) ? Row::string($departments[$departmentId], 'name') : '',
            );
        }

        usort(
            $result,
            static fn (AppointmentDetails $a, AppointmentDetails $b): int => $a->appointment->startsAt <=> $b->appointment->startsAt,
        );

        return $result;
    }

    /**
     * @return list<Appointment>
     */
    private function activeWhere(string $column, int $id, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $result = [];

        foreach ($this->store->rows(self::TABLE) as $row) {
            if (($row[$column] ?? null) !== $id || ($row['status'] ?? null) !== AppointmentStatus::Booked->value) {
                continue;
            }

            $appointment = self::hydrate($row);
            if ($appointment->overlaps($from, $to)) {
                $result[] = $appointment;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): Appointment
    {
        return new Appointment(
            Row::int($row, 'id'),
            Row::int($row, 'patient_id'),
            Row::int($row, 'doctor_id'),
            Row::date($row, 'starts_at'),
            Row::date($row, 'ends_at'),
            AppointmentStatus::from(Row::string($row, 'status')),
            Row::nullableString($row, 'patient_note'),
            Row::date($row, 'created_at'),
            Row::nullableDate($row, 'cancelled_at'),
        );
    }
}
