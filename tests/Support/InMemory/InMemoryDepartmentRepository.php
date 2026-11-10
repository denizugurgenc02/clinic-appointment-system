<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\Department;
use App\Repository\Contract\DepartmentRepositoryInterface;

final class InMemoryDepartmentRepository implements DepartmentRepositoryInterface
{
    public function __construct(private readonly InMemoryDatabase $db)
    {
    }

    public function all(): array
    {
        $all = array_values(array_map(static fn (Department $d): Department => clone $d, $this->db->departments));
        usort($all, static fn (Department $a, Department $b): int => strcmp($a->name, $b->name));

        return $all;
    }

    public function find(int $id): ?Department
    {
        return isset($this->db->departments[$id]) ? clone $this->db->departments[$id] : null;
    }

    public function findByName(string $name): ?Department
    {
        $needle = mb_strtolower(trim($name));

        foreach ($this->db->departments as $department) {
            if (mb_strtolower($department->name) === $needle) {
                return clone $department;
            }
        }

        return null;
    }

    public function create(Department $department): Department
    {
        $department->id = $this->db->nextId('departments');
        $this->db->departments[$department->id] = clone $department;

        return $department;
    }

    public function update(Department $department): Department
    {
        $this->db->departments[(int) $department->id] = clone $department;

        return $department;
    }
}
