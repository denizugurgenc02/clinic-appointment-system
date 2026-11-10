<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Model\Schedule;

interface ScheduleRepositoryInterface
{
    /**
     * @return list<Schedule> Gün ve başlangıç saatine göre sıralı
     */
    public function forDoctor(int $doctorId): array;

    public function find(int $id): ?Schedule;

    public function add(Schedule $schedule): Schedule;

    public function delete(int $id): void;
}
