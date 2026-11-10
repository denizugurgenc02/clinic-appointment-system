<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\Doctor;
use App\Repository\Contract\DoctorRepositoryInterface;

final class InMemoryDoctorRepository implements DoctorRepositoryInterface
{
    public function __construct(private readonly InMemoryDatabase $db)
    {
    }

    public function find(int $id): ?Doctor
    {
        $user = $this->db->users[$id] ?? null;

        return $user instanceof Doctor ? $this->withDepartmentName($user) : null;
    }

    public function all(?int $departmentId = null): array
    {
        $result = [];
        foreach ($this->db->users as $user) {
            if ($user instanceof Doctor && ($departmentId === null || $user->departmentId === $departmentId)) {
                $result[] = $this->withDepartmentName($user);
            }
        }

        usort($result, static fn (Doctor $a, Doctor $b): int => strcmp($a->name, $b->name));

        return $result;
    }

    public function create(Doctor $doctor): Doctor
    {
        $doctor->id = $this->db->nextId('users');
        $this->db->users[$doctor->id] = clone $doctor;

        return $doctor;
    }

    /**
     * JSON/SQL uygulamasındaki doctors ⋈ departments birleşiminin karşılığı.
     */
    private function withDepartmentName(Doctor $doctor): Doctor
    {
        $copy = clone $doctor;
        $copy->departmentName = $this->db->departments[$doctor->departmentId]->name ?? '';

        return $copy;
    }
}
