<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\AppointmentFilter;
use App\Model\DailyReport;
use App\Model\Enum\AppointmentStatus;
use App\Repository\Contract\AppointmentRepositoryInterface;
use App\Repository\Contract\DoctorRepositoryInterface;
use DateTimeImmutable;

/**
 * Günlük randevu raporu (sekreter). Liste, randevu + hasta + doktor + poliklinik
 * birleşiminden (Aşama 3'te JOIN) gelir; kapasite slot üreticisinden hesaplanır.
 */
final class ReportService
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointments,
        private readonly DoctorRepositoryInterface $doctors,
        private readonly SlotService $slots,
    ) {
    }

    public function daily(DateTimeImmutable $date, ?int $departmentId = null): DailyReport
    {
        $day = $date->setTime(0, 0);
        $rows = $this->appointments->search(new AppointmentFilter(departmentId: $departmentId, dateFrom: $day, dateTo: $day));

        $counts = array_fill_keys(array_map(static fn (AppointmentStatus $s): string => $s->value, AppointmentStatus::cases()), 0);
        $departments = [];

        foreach ($this->doctors->all($departmentId) as $doctor) {
            $name = $doctor->departmentName;
            $departments[$name] ??= ['department' => $name, 'appointments' => 0, 'capacity' => 0];
            $departments[$name]['capacity'] += $this->slots->dayCapacity($doctor, $day);
        }

        foreach ($rows as $row) {
            $counts[$row->appointment->status->value]++;

            if ($row->appointment->status !== AppointmentStatus::Cancelled) {
                $departments[$row->departmentName] ??= ['department' => $row->departmentName, 'appointments' => 0, 'capacity' => 0];
                $departments[$row->departmentName]['appointments']++;
            }
        }

        ksort($departments);

        return new DailyReport(
            $day,
            $rows,
            $counts,
            array_sum(array_column($departments, 'capacity')),
            array_values($departments),
        );
    }
}
