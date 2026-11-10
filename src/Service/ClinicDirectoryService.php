<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Department;
use App\Model\Doctor;
use App\Model\Patient;
use App\Repository\Contract\DepartmentRepositoryInterface;
use App\Repository\Contract\DoctorRepositoryInterface;
use App\Repository\Contract\PatientRepositoryInterface;
use App\Service\Exception\NotFoundException;

/**
 * Poliklinik, doktor ve hasta listeleri (seçim kutuları ve aramalar için).
 * Controller'lar repository'ye doğrudan erişmez; bu servis üzerinden okur.
 */
final class ClinicDirectoryService
{
    public function __construct(
        private readonly DepartmentRepositoryInterface $departments,
        private readonly DoctorRepositoryInterface $doctors,
        private readonly PatientRepositoryInterface $patients,
    ) {
    }

    /**
     * @return list<Department>
     */
    public function departments(): array
    {
        return $this->departments->all();
    }

    /**
     * @return list<Doctor>
     */
    public function doctors(?int $departmentId = null): array
    {
        return $this->doctors->all($departmentId);
    }

    public function doctor(int $id): Doctor
    {
        return $this->doctors->find($id) ?? throw new NotFoundException('Doktor bulunamadı.');
    }

    public function patient(int $id): Patient
    {
        return $this->patients->find($id) ?? throw new NotFoundException('Hasta bulunamadı.');
    }

    /**
     * @return list<Patient>
     */
    public function searchPatients(?string $term): array
    {
        return $this->patients->search($term);
    }
}
