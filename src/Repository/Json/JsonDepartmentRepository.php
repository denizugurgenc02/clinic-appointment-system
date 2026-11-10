<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Model\Department;
use App\Repository\Contract\DepartmentRepositoryInterface;

final class JsonDepartmentRepository implements DepartmentRepositoryInterface
{
    public function __construct(private readonly JsonStore $store)
    {
    }

    public function all(): array
    {
        $departments = array_map(self::hydrate(...), $this->store->rows('departments'));
        usort($departments, static fn (Department $a, Department $b): int => strcmp($a->name, $b->name));

        return $departments;
    }

    public function find(int $id): ?Department
    {
        $row = $this->store->find('departments', $id);

        return $row === null ? null : self::hydrate($row);
    }

    public function findByName(string $name): ?Department
    {
        $needle = mb_strtolower(trim($name));

        foreach ($this->all() as $department) {
            if (mb_strtolower($department->name) === $needle) {
                return $department;
            }
        }

        return null;
    }

    public function update(Department $department): Department
    {
        $this->store->update('departments', (int) $department->id, [
            'name' => $department->name,
            'description' => $department->description,
        ]);

        return $department;
    }

    public function create(Department $department): Department
    {
        $department->id = $this->store->insert('departments', [
            'name' => $department->name,
            'description' => $department->description,
        ]);

        return $department;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): Department
    {
        return new Department(Row::int($row, 'id'), Row::string($row, 'name'), Row::nullableString($row, 'description') ?? '');
    }
}
