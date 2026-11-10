<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Model\Appointment;
use App\Model\Schedule;
use App\Model\TimeOff;
use App\Repository\Contract\AppointmentRepositoryInterface;
use App\Repository\Contract\DoctorRepositoryInterface;
use App\Repository\Contract\ScheduleRepositoryInterface;
use App\Repository\Contract\TimeOffRepositoryInterface;
use App\Repository\Contract\TransactionManagerInterface;
use App\Service\Exception\DomainRuleException;
use App\Service\Exception\NotFoundException;
use App\Service\Exception\ValidationException;
use DateTimeImmutable;

/**
 * Sekreterin doktor çalışma takvimini ve izinlerini yönetmesi.
 */
final class ScheduleService
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly TimeOffRepositoryInterface $timeOffs,
        private readonly DoctorRepositoryInterface $doctors,
        private readonly AppointmentRepositoryInterface $appointments,
        private readonly TransactionManagerInterface $tx,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return list<Schedule>
     */
    public function schedules(int $doctorId): array
    {
        return $this->schedules->forDoctor($doctorId);
    }

    /**
     * Bitmemiş izinler.
     *
     * @return list<TimeOff>
     */
    public function upcomingTimeOffs(int $doctorId): array
    {
        $now = $this->clock->now();

        return array_values(array_filter(
            $this->timeOffs->forDoctor($doctorId),
            static fn (TimeOff $t): bool => $t->endsAt > $now,
        ));
    }

    public function addRow(int $doctorId, int $dayOfWeek, string $startTime, string $endTime): Schedule
    {
        $errors = [];
        if ($dayOfWeek < 1 || $dayOfWeek > 7) {
            $errors['day_of_week'] = 'Geçerli bir gün seçin.';
        }
        foreach (['start_time' => $startTime, 'end_time' => $endTime] as $field => $time) {
            if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
                $errors[$field] = 'Saat SS:DD biçiminde olmalı.';
            }
        }
        if ($errors === [] && $startTime >= $endTime) {
            $errors['end_time'] = 'Bitiş saati başlangıçtan sonra olmalı.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->tx->run(function () use ($doctorId, $dayOfWeek, $startTime, $endTime): Schedule {
            $this->doctors->find($doctorId) ?? throw new NotFoundException('Doktor bulunamadı.');
            $row = new Schedule(null, $doctorId, $dayOfWeek, $startTime, $endTime);

            // K3: aynı gündeki satırlar çakışamaz
            foreach ($this->schedules->forDoctor($doctorId) as $existing) {
                if ($existing->overlaps($row)) {
                    throw new DomainRuleException(sprintf(
                        '%s günü %s-%s satırıyla çakışıyor.',
                        $existing->dayName(),
                        $existing->startTime,
                        $existing->endTime,
                    ));
                }
            }

            return $this->schedules->add($row);
        });
    }

    public function deleteRow(int $scheduleId): Schedule
    {
        return $this->tx->run(function () use ($scheduleId): Schedule {
            $row = $this->schedules->find($scheduleId) ?? throw new NotFoundException('Takvim satırı bulunamadı.');
            $this->schedules->delete($scheduleId);

            return $row;
        });
    }

    /**
     * İzin ekler ve o aralıkta kalan aktif randevuları döndürür (sekreter uyarılır).
     *
     * @return array{timeOff: TimeOff, conflicts: list<Appointment>}
     */
    public function addTimeOff(int $doctorId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, string $reason): array
    {
        if ($endsAt <= $startsAt) {
            throw new ValidationException(['ends_at' => 'İzin bitişi başlangıçtan sonra olmalı.']);
        }

        $reason = trim($reason);
        if (mb_strlen($reason) > 200) {
            throw new ValidationException(['reason' => 'Açıklama en fazla 200 karakter olabilir.']);
        }

        return $this->tx->run(function () use ($doctorId, $startsAt, $endsAt, $reason): array {
            $this->doctors->find($doctorId) ?? throw new NotFoundException('Doktor bulunamadı.');

            return [
                'timeOff' => $this->timeOffs->add(new TimeOff(null, $doctorId, $startsAt, $endsAt, $reason)),
                'conflicts' => $this->appointments->activeForDoctorBetween($doctorId, $startsAt, $endsAt),
            ];
        });
    }

    public function deleteTimeOff(int $timeOffId): TimeOff
    {
        return $this->tx->run(function () use ($timeOffId): TimeOff {
            $timeOff = $this->timeOffs->find($timeOffId) ?? throw new NotFoundException('İzin kaydı bulunamadı.');
            $this->timeOffs->delete($timeOffId);

            return $timeOff;
        });
    }
}
