<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Model\Doctor;
use App\Repository\Contract\DoctorRepositoryInterface;

final class JsonDoctorRepository implements DoctorRepositoryInterface
{
    public function __construct(private readonly JsonStore $store)
    {
    }

    public function find(int $id): ?Doctor
    {
        foreach ($this->all() as $doctor) {
            if ($doctor->id === $id) {
                return $doctor;
            }
        }

        return null;
    }

    public function all(?int $departmentId = null): array
    {
        // users ⋈ doctors ⋈ departments
        $users = Row::indexBy($this->store->rows('users'));
        $departments = Row::indexBy($this->store->rows('departments'));
        $result = [];

        foreach ($this->store->rows('doctors') as $doctorRow) {
            $user = $users[Row::int($doctorRow, 'user_id')] ?? null;
            $deptId = Row::int($doctorRow, 'department_id');

            if ($user === null || ($departmentId !== null && $deptId !== $departmentId)) {
                continue;
            }

            $deptName = isset($departments[$deptId]) ? Row::string($departments[$deptId], 'name') : '';
            $result[] = Row::toDoctor($user, $doctorRow, $deptName);
        }

        usort($result, static fn (Doctor $a, Doctor $b): int => strcmp($a->name, $b->name));

        return $result;
    }

    public function create(Doctor $doctor): Doctor
    {
        $doctor->id = $this->store->insert('users', Row::fromUser($doctor));
        $this->store->insertRaw('doctors', [
            'user_id' => $doctor->id,
            'department_id' => $doctor->departmentId,
            'title' => $doctor->title,
            'slot_minutes' => $doctor->slotMinutes,
        ]);

        return $doctor;
    }
}
