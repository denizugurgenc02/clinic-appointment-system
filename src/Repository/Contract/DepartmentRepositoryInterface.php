<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Model\Department;

interface DepartmentRepositoryInterface
{
    /**
     * @return list<Department> Ada göre sıralı
     */
    public function all(): array;

    public function find(int $id): ?Department;

    /**
     * Büyük/küçük harf duyarsız ad araması (benzersiz ad kontrolü için).
     */
    public function findByName(string $name): ?Department;

    public function create(Department $department): Department;

    public function update(Department $department): Department;
}
