<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\Patient;
use App\Repository\Contract\PatientRepositoryInterface;

final class InMemoryPatientRepository implements PatientRepositoryInterface
{
    public function __construct(private readonly InMemoryDatabase $db)
    {
    }

    public function find(int $id): ?Patient
    {
        $user = $this->db->users[$id] ?? null;

        return $user instanceof Patient ? clone $user : null;
    }

    public function create(Patient $patient): Patient
    {
        $patient->id = $this->db->nextId('users');
        $this->db->users[$patient->id] = clone $patient;

        return $patient;
    }

    public function search(?string $term, int $limit = 50): array
    {
        $needle = mb_strtolower(trim((string) $term));
        $result = [];

        foreach ($this->db->users as $user) {
            $haystack = mb_strtolower($user->name . ' ' . $user->email . ' ' . ($user instanceof Patient ? $user->phone : ''));
            if ($user instanceof Patient && ($needle === '' || str_contains($haystack, $needle))) {
                $result[] = clone $user;
            }
        }

        usort($result, static fn (Patient $a, Patient $b): int => strcmp($a->name, $b->name));

        return array_slice($result, 0, $limit);
    }
}
