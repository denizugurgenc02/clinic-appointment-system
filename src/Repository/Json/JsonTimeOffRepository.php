<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Model\TimeOff;
use App\Repository\Contract\TimeOffRepositoryInterface;
use DateTimeImmutable;

final class JsonTimeOffRepository implements TimeOffRepositoryInterface
{
    public function __construct(private readonly JsonStore $store)
    {
    }

    public function forDoctor(int $doctorId, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): array
    {
        $result = [];

        foreach ($this->store->rows('time_off') as $row) {
            if (($row['doctor_id'] ?? null) !== $doctorId) {
                continue;
            }

            $timeOff = self::hydrate($row);
            if ($from !== null && $to !== null && !$timeOff->overlaps($from, $to)) {
                continue;
            }

            $result[] = $timeOff;
        }

        usort($result, static fn (TimeOff $a, TimeOff $b): int => $a->startsAt <=> $b->startsAt);

        return $result;
    }

    public function find(int $id): ?TimeOff
    {
        $row = $this->store->find('time_off', $id);

        return $row === null ? null : self::hydrate($row);
    }

    public function add(TimeOff $timeOff): TimeOff
    {
        $timeOff->id = $this->store->insert('time_off', [
            'doctor_id' => $timeOff->doctorId,
            'starts_at' => Row::formatDate($timeOff->startsAt),
            'ends_at' => Row::formatDate($timeOff->endsAt),
            'reason' => $timeOff->reason,
        ]);

        return $timeOff;
    }

    public function delete(int $id): void
    {
        $this->store->delete('time_off', $id);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): TimeOff
    {
        return new TimeOff(
            Row::int($row, 'id'),
            Row::int($row, 'doctor_id'),
            Row::date($row, 'starts_at'),
            Row::date($row, 'ends_at'),
            Row::nullableString($row, 'reason') ?? '',
        );
    }
}
