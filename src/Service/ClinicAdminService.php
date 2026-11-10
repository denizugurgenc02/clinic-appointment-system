<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Model\Department;
use App\Model\Doctor;
use App\Model\Enum\UserRole;
use App\Model\User;
use App\Repository\Contract\DepartmentRepositoryInterface;
use App\Repository\Contract\DoctorRepositoryInterface;
use App\Repository\Contract\TransactionManagerInterface;
use App\Repository\Contract\UserRepositoryInterface;
use App\Service\Exception\NotFoundException;
use App\Service\Exception\ValidationException;

/**
 * Süper yönetici işlemleri: poliklinik ekleme/güncelleme, doktor ve sekreter hesabı açma.
 *
 * Veriyi yalnızca repository arayüzleri üzerinden yazar; Aşama 3'te veritabanına geçildiğinde
 * bu sınıf değişmez (open-closed).
 */
final class ClinicAdminService
{
    public function __construct(
        private readonly DepartmentRepositoryInterface $departments,
        private readonly DoctorRepositoryInterface $doctors,
        private readonly UserRepositoryInterface $users,
        private readonly TransactionManagerInterface $tx,
        private readonly ClockInterface $clock,
    ) {
    }

    public function createDepartment(string $name, string $description): Department
    {
        [$name, $description] = $this->validateDepartment($name, $description);

        return $this->tx->run(function () use ($name, $description): Department {
            if ($this->departments->findByName($name) !== null) {
                throw new ValidationException(['name' => 'Bu adda bir poliklinik zaten var.']);
            }

            return $this->departments->create(new Department(null, $name, $description));
        });
    }

    public function updateDepartment(int $id, string $name, string $description): Department
    {
        [$name, $description] = $this->validateDepartment($name, $description);

        return $this->tx->run(function () use ($id, $name, $description): Department {
            $department = $this->departments->find($id) ?? throw new NotFoundException('Poliklinik bulunamadı.');
            $existing = $this->departments->findByName($name);

            if ($existing !== null && $existing->id !== $id) {
                throw new ValidationException(['name' => 'Bu adda bir poliklinik zaten var.']);
            }

            $department->name = $name;
            $department->description = $description;

            return $this->departments->update($department);
        });
    }

    /**
     * Doktor hesabı açar. Çalışma takvimi sonra sekreter/yönetici tarafından eklenir.
     */
    public function createDoctor(
        string $name,
        string $email,
        string $password,
        int $departmentId,
        string $title,
        int $slotMinutes,
    ): Doctor {
        $errors = $this->validateAccount($name, $email, $password);
        if ($slotMinutes < 5 || $slotMinutes > 120) {
            $errors['slot_minutes'] = 'Randevu süresi 5-120 dakika olmalı.';
        }
        if (mb_strlen(trim($title)) > 50) {
            $errors['title'] = 'Unvan en fazla 50 karakter olabilir.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->tx->run(function () use ($name, $email, $password, $departmentId, $title, $slotMinutes): Doctor {
            $department = $this->departments->find($departmentId)
                ?? throw new ValidationException(['department_id' => 'Geçerli bir poliklinik seçin.']);
            $this->assertEmailIsFree($email);

            return $this->doctors->create(new Doctor(
                null,
                trim($name),
                mb_strtolower(trim($email)),
                password_hash($password, PASSWORD_DEFAULT),
                $departmentId,
                trim($title),
                $slotMinutes,
                null,
                $this->clock->now(),
                $department->name,
            ));
        });
    }

    public function createSecretary(string $name, string $email, string $password): User
    {
        $errors = $this->validateAccount($name, $email, $password);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->tx->run(function () use ($name, $email, $password): User {
            $this->assertEmailIsFree($email);

            return $this->users->create(new User(
                null,
                trim($name),
                mb_strtolower(trim($email)),
                password_hash($password, PASSWORD_DEFAULT),
                UserRole::Secretary,
                null,
                $this->clock->now(),
            ));
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function validateDepartment(string $name, string $description): array
    {
        $name = trim($name);
        $description = trim($description);
        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Poliklinik adı 2-100 karakter olmalı.';
        }
        if (mb_strlen($description) > 255) {
            $errors['description'] = 'Açıklama en fazla 255 karakter olabilir.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [$name, $description];
    }

    /**
     * @return array<string, string>
     */
    private function validateAccount(string $name, string $email, string $password): array
    {
        $errors = [];
        if (mb_strlen(trim($name)) < 3 || mb_strlen(trim($name)) > 100) {
            $errors['name'] = 'Ad soyad 3-100 karakter olmalı.';
        }
        if (filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Geçerli bir e-posta adresi girin.';
        }
        if (mb_strlen($password) < 8 || preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            $errors['password'] = 'Şifre en az 8 karakter olmalı ve harf ile rakam içermeli.';
        }

        return $errors;
    }

    private function assertEmailIsFree(string $email): void
    {
        if ($this->users->findByEmail($email) !== null) {
            throw new ValidationException(['email' => 'Bu e-posta adresiyle kayıtlı bir hesap var.']);
        }
    }
}
