<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Model\Appointment;
use App\Model\AppointmentDetails;
use App\Model\AppointmentFilter;
use DateTimeImmutable;

interface AppointmentRepositoryInterface
{
    public function find(int $id): ?Appointment;

    /**
     * id null ise ekler (id atar), değilse günceller.
     */
    public function save(Appointment $appointment): Appointment;

    /**
     * Doktorun [from, to) aralığıyla kesişen aktif (Booked) randevuları.
     * Aşama 3: SELECT ... FOR UPDATE ile kilitlenerek okunur.
     *
     * @return list<Appointment>
     */
    public function activeForDoctorBetween(int $doctorId, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Hastanın [from, to) aralığıyla kesişen aktif randevuları (K7).
     *
     * @return list<Appointment>
     */
    public function activeForPatientBetween(int $patientId, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Randevu + hasta + doktor + poliklinik (Aşama 3'te JOIN). Başlangıca göre sıralı.
     *
     * @return list<AppointmentDetails>
     */
    public function search(AppointmentFilter $filter): array;
}
