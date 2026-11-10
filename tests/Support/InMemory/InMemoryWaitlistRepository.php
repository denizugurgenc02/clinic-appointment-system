<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\Doctor;
use App\Model\Enum\WaitlistStatus;
use App\Model\WaitlistDetails;
use App\Model\WaitlistEntry;
use App\Repository\Contract\WaitlistRepositoryInterface;
use DateTimeImmutable;

final class InMemoryWaitlistRepository implements WaitlistRepositoryInterface
{
    public function __construct(private readonly InMemoryDatabase $db)
    {
    }

    public function find(int $id): ?WaitlistEntry
    {
        return isset($this->db->waitlist[$id]) ? clone $this->db->waitlist[$id] : null;
    }

    public function save(WaitlistEntry $entry): WaitlistEntry
    {
        $entry->id ??= $this->db->nextId('waitlist');
        $this->db->waitlist[$entry->id] = clone $entry;

        return $entry;
    }

    public function openForPatientAndDoctor(int $patientId, int $doctorId): ?WaitlistEntry
    {
        foreach ($this->db->waitlist as $entry) {
            if ($entry->patientId === $patientId && $entry->doctorId === $doctorId && $entry->status->isOpen()) {
                return clone $entry;
            }
        }

        return null;
    }

    public function waitingQueue(int $doctorId): array
    {
        return array_values(array_filter(
            $this->fifo(),
            static fn (WaitlistEntry $e): bool => $e->doctorId === $doctorId && $e->status === WaitlistStatus::Waiting,
        ));
    }

    public function offeredForSlot(int $doctorId, DateTimeImmutable $start): ?WaitlistEntry
    {
        foreach ($this->db->waitlist as $entry) {
            if ($entry->doctorId === $doctorId && $entry->status === WaitlistStatus::Offered && $entry->offeredStartsAt == $start) {
                return clone $entry;
            }
        }

        return null;
    }

    public function expiredOffers(DateTimeImmutable $now): array
    {
        return array_values(array_filter($this->fifo(), static fn (WaitlistEntry $e): bool => $e->isOfferExpired($now)));
    }

    public function details(?int $patientId = null, bool $onlyOpen = false): array
    {
        $positions = [];
        $counter = [];
        foreach ($this->fifo() as $entry) {
            if ($entry->status === WaitlistStatus::Waiting) {
                $counter[$entry->doctorId] = ($counter[$entry->doctorId] ?? 0) + 1;
                $positions[(int) $entry->id] = $counter[$entry->doctorId];
            }
        }

        $result = [];
        foreach ($this->db->waitlist as $entry) {
            if (($patientId !== null && $entry->patientId !== $patientId) || ($onlyOpen && !$entry->status->isOpen())) {
                continue;
            }

            $doctor = $this->db->users[$entry->doctorId] ?? null;
            $result[] = new WaitlistDetails(
                clone $entry,
                $this->db->users[$entry->patientId]->name ?? '?',
                $doctor instanceof Doctor ? $doctor->displayName() : '?',
                $doctor instanceof Doctor ? ($this->db->departments[$doctor->departmentId]->name ?? '') : '',
                $positions[(int) $entry->id] ?? null,
            );
        }

        usort($result, static fn (WaitlistDetails $a, WaitlistDetails $b): int => $b->entry->createdAt <=> $a->entry->createdAt);

        return $result;
    }

    /**
     * FIFO: created_at, eşitlikte id.
     *
     * @return list<WaitlistEntry>
     */
    private function fifo(): array
    {
        $entries = array_values(array_map(static fn (WaitlistEntry $e): WaitlistEntry => clone $e, $this->db->waitlist));
        usort($entries, static fn (WaitlistEntry $a, WaitlistEntry $b): int => [$a->createdAt, $a->id] <=> [$b->createdAt, $b->id]);

        return $entries;
    }
}
