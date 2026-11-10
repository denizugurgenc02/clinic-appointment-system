<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Model\Schedule;
use App\Repository\Contract\ScheduleRepositoryInterface;

final class JsonScheduleRepository implements ScheduleRepositoryInterface
{
    public function __construct(private readonly JsonStore $store)
    {
    }

    public function forDoctor(int $doctorId): array
    {
        $rows = array_filter($this->store->rows('schedules'), static fn (array $r): bool => ($r['doctor_id'] ?? null) === $doctorId);
        $schedules = array_values(array_map(self::hydrate(...), $rows));

        usort($schedules, static fn (Schedule $a, Schedule $b): int => [$a->dayOfWeek, $a->startTime] <=> [$b->dayOfWeek, $b->startTime]);

        return $schedules;
    }

    public function find(int $id): ?Schedule
    {
        $row = $this->store->find('schedules', $id);

        return $row === null ? null : self::hydrate($row);
    }

    public function add(Schedule $schedule): Schedule
    {
        $schedule->id = $this->store->insert('schedules', [
            'doctor_id' => $schedule->doctorId,
            'day_of_week' => $schedule->dayOfWeek,
            'start_time' => $schedule->startTime,
            'end_time' => $schedule->endTime,
        ]);

        return $schedule;
    }

    public function delete(int $id): void
    {
        $this->store->delete('schedules', $id);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): Schedule
    {
        return new Schedule(
            Row::int($row, 'id'),
            Row::int($row, 'doctor_id'),
            Row::int($row, 'day_of_week'),
            Row::string($row, 'start_time'),
            Row::string($row, 'end_time'),
        );
    }
}
