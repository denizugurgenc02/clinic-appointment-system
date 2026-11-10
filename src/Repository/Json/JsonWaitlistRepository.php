<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Model\Enum\WaitlistStatus;
use App\Model\WaitlistDetails;
use App\Model\WaitlistEntry;
use App\Repository\Contract\WaitlistRepositoryInterface;
use DateTimeImmutable;

final class JsonWaitlistRepository implements WaitlistRepositoryInterface
{
    private const TABLE = 'waitlist';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function find(int $id): ?WaitlistEntry
    {
        $row = $this->store->find(self::TABLE, $id);

        return $row === null ? null : self::hydrate($row);
    }

    public function save(WaitlistEntry $entry): WaitlistEntry
    {
        $row = [
            'patient_id' => $entry->patientId,
            'doctor_id' => $entry->doctorId,
            'desired_date' => Row::formatDate($entry->desiredDate, Row::DATE),
            'status' => $entry->status->value,
            'created_at' => Row::formatDate($entry->createdAt),
            'offered_starts_at' => Row::formatDate($entry->offeredStartsAt),
            'offered_ends_at' => Row::formatDate($entry->offeredEndsAt),
            'offer_expires_at' => Row::formatDate($entry->offerExpiresAt),
        ];

        if ($entry->id === null) {
            $entry->id = $this->store->insert(self::TABLE, $row);
        } else {
            $this->store->update(self::TABLE, $entry->id, $row);
        }

        return $entry;
    }

    public function openForPatientAndDoctor(int $patientId, int $doctorId): ?WaitlistEntry
    {
        foreach ($this->all() as $entry) {
            if ($entry->patientId === $patientId && $entry->doctorId === $doctorId && $entry->status->isOpen()) {
                return $entry;
            }
        }

        return null;
    }

    public function waitingQueue(int $doctorId): array
    {
        $queue = array_values(array_filter(
            $this->all(),
            static fn (WaitlistEntry $e): bool => $e->doctorId === $doctorId && $e->status === WaitlistStatus::Waiting,
        ));

        usort($queue, self::fifo(...));

        return $queue;
    }

    public function offeredForSlot(int $doctorId, DateTimeImmutable $start): ?WaitlistEntry
    {
        foreach ($this->all() as $entry) {
            if (
                $entry->doctorId === $doctorId
                && $entry->status === WaitlistStatus::Offered
                && $entry->offeredStartsAt == $start
            ) {
                return $entry;
            }
        }

        return null;
    }

    public function expiredOffers(DateTimeImmutable $now): array
    {
        return array_values(array_filter($this->all(), static fn (WaitlistEntry $e): bool => $e->isOfferExpired($now)));
    }

    public function details(?int $patientId = null, bool $onlyOpen = false): array
    {
        $users = Row::indexBy($this->store->rows('users'));
        $doctors = Row::indexBy($this->store->rows('doctors'), 'user_id');
        $departments = Row::indexBy($this->store->rows('departments'));
        $entries = $this->all();

        // Her doktorun bekleme sırası (1 = sıradaki)
        $positions = [];
        $waiting = array_filter($entries, static fn (WaitlistEntry $e): bool => $e->status === WaitlistStatus::Waiting);
        usort($waiting, self::fifo(...));
        foreach ($waiting as $entry) {
            $positions[$entry->doctorId] = ($positions[$entry->doctorId] ?? 0) + 1;
            $positions['entry_' . $entry->id] = $positions[$entry->doctorId];
        }

        $result = [];
        foreach ($entries as $entry) {
            if (($patientId !== null && $entry->patientId !== $patientId) || ($onlyOpen && !$entry->status->isOpen())) {
                continue;
            }

            $doctor = $doctors[$entry->doctorId] ?? null;
            $departmentId = $doctor === null ? 0 : Row::int($doctor, 'department_id');

            $result[] = new WaitlistDetails(
                $entry,
                isset($users[$entry->patientId]) ? Row::string($users[$entry->patientId], 'name') : '?',
                $doctor === null || !isset($users[$entry->doctorId])
                    ? '?'
                    : trim(Row::string($doctor, 'title') . ' ' . Row::string($users[$entry->doctorId], 'name')),
                isset($departments[$departmentId]) ? Row::string($departments[$departmentId], 'name') : '',
                $positions['entry_' . $entry->id] ?? null,
            );
        }

        usort($result, static fn (WaitlistDetails $a, WaitlistDetails $b): int => $b->entry->createdAt <=> $a->entry->createdAt);

        return $result;
    }

    /**
     * @return list<WaitlistEntry>
     */
    private function all(): array
    {
        return array_map(self::hydrate(...), $this->store->rows(self::TABLE));
    }

    private static function fifo(WaitlistEntry $a, WaitlistEntry $b): int
    {
        return [$a->createdAt, $a->id] <=> [$b->createdAt, $b->id];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): WaitlistEntry
    {
        return new WaitlistEntry(
            Row::int($row, 'id'),
            Row::int($row, 'patient_id'),
            Row::int($row, 'doctor_id'),
            Row::nullableDate($row, 'desired_date'),
            WaitlistStatus::from(Row::string($row, 'status')),
            Row::date($row, 'created_at'),
            Row::nullableDate($row, 'offered_starts_at'),
            Row::nullableDate($row, 'offered_ends_at'),
            Row::nullableDate($row, 'offer_expires_at'),
        );
    }
}
