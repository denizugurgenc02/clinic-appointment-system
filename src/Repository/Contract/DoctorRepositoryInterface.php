<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Model\Doctor;

interface DoctorRepositoryInterface
{
    public function find(int $id): ?Doctor;

    /**
     * @return list<Doctor> Ada göre sıralı
     */
    public function all(?int $departmentId = null): array;

    /**
     * users + doctors satırlarını birlikte oluşturur.
     */
    public function create(Doctor $doctor): Doctor;
}
