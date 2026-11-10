<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\Schedule;
use App\Repository\Contract\ScheduleRepositoryInterface;

final class InMemoryScheduleRepository implements ScheduleRepositoryInterface
{
    public function __construct(private readonly InMemoryDatabase $db)
    {
    }

    public function forDoctor(int $doctorId): array
    {
        $rows = [];
        foreach ($this->db->schedules as $schedule) {
            if ($schedule->doctorId === $doctorId) {
                $rows[] = clone $schedule;
            }
        }

        usort($rows, static fn (Schedule $a, Schedule $b): int => [$a->dayOfWeek, $a->startTime] <=> [$b->dayOfWeek, $b->startTime]);

        return $rows;
    }

    public function find(int $id): ?Schedule
    {
        return isset($this->db->schedules[$id]) ? clone $this->db->schedules[$id] : null;
    }

    public function add(Schedule $schedule): Schedule
    {
        $schedule->id = $this->db->nextId('schedules');
        $this->db->schedules[$schedule->id] = clone $schedule;

        return $schedule;
    }

    public function delete(int $id): void
    {
        unset($this->db->schedules[$id]);
    }
}
