<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\TimeOff;
use App\Repository\Contract\TimeOffRepositoryInterface;
use DateTimeImmutable;

final class InMemoryTimeOffRepository implements TimeOffRepositoryInterface
{
    public function __construct(private readonly InMemoryDatabase $db)
    {
    }

    public function forDoctor(int $doctorId, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): array
    {
        $result = [];
        foreach ($this->db->timeOffs as $timeOff) {
            $inRange = $from === null || $to === null || $timeOff->overlaps($from, $to);
            if ($timeOff->doctorId === $doctorId && $inRange) {
                $result[] = clone $timeOff;
            }
        }

        usort($result, static fn (TimeOff $a, TimeOff $b): int => $a->startsAt <=> $b->startsAt);

        return $result;
    }

    public function find(int $id): ?TimeOff
    {
        return isset($this->db->timeOffs[$id]) ? clone $this->db->timeOffs[$id] : null;
    }

    public function add(TimeOff $timeOff): TimeOff
    {
        $timeOff->id = $this->db->nextId('time_off');
        $this->db->timeOffs[$timeOff->id] = clone $timeOff;

        return $timeOff;
    }

    public function delete(int $id): void
    {
        unset($this->db->timeOffs[$id]);
    }
}
