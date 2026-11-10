<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Model\TimeOff;
use DateTimeImmutable;

interface TimeOffRepositoryInterface
{
    /**
     * Doktorun [from, to) aralığıyla kesişen izinleri (aralık verilmezse tümü), başlangıca göre sıralı.
     *
     * @return list<TimeOff>
     */
    public function forDoctor(int $doctorId, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): array;

    public function find(int $id): ?TimeOff;

    public function add(TimeOff $timeOff): TimeOff;

    public function delete(int $id): void;
}
