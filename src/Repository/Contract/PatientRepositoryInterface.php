<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Model\Patient;

interface PatientRepositoryInterface
{
    public function find(int $id): ?Patient;

    /**
     * users + patients satırlarını birlikte oluşturur.
     */
    public function create(Patient $patient): Patient;

    /**
     * Ad, e-posta veya telefonda geçen metne göre arar (boşsa tümü).
     *
     * @return list<Patient>
     */
    public function search(?string $term, int $limit = 50): array;
}
