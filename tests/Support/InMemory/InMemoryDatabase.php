<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\Appointment;
use App\Model\Department;
use App\Model\Schedule;
use App\Model\TimeOff;
use App\Model\User;
use App\Model\WaitlistEntry;

/**
 * Testler için bellekte tutulan "veritabanı". Bellek içi repository'ler bu nesneyi paylaşır;
 * böylece birleştirme (JOIN) gerektiren okumalar (randevu + hasta + doktor) yapılabilir.
 *
 * Kalıcılığı taklit etmek için repository'ler nesneleri saklarken ve döndürürken KOPYALAR:
 * servisin elindeki nesneyi değiştirmesi, save() çağrılmadan "kaydedilmiş" sayılmaz.
 */
final class InMemoryDatabase
{
    /** @var array<int, User> Patient ve Doctor nesneleri de burada (kalıtım) */
    public array $users = [];

    /** @var array<int, Department> */
    public array $departments = [];

    /** @var array<int, Schedule> */
    public array $schedules = [];

    /** @var array<int, TimeOff> */
    public array $timeOffs = [];

    /** @var array<int, Appointment> */
    public array $appointments = [];

    /** @var array<int, WaitlistEntry> */
    public array $waitlist = [];

    /** @var array<string, int> */
    private array $sequences = [];

    /**
     * Tablo başına otomatik artan id (1'den başlar; 0 süper yöneticiye ayrılmıştır).
     */
    public function nextId(string $table): int
    {
        $this->sequences[$table] = ($this->sequences[$table] ?? 0) + 1;

        return $this->sequences[$table];
    }

    /**
     * Transaction geri alma için tüm tabloların derin kopyası.
     */
    public function snapshot(): self
    {
        $copy = clone $this;
        $copy->users = self::cloneAll($this->users);
        $copy->departments = self::cloneAll($this->departments);
        $copy->schedules = self::cloneAll($this->schedules);
        $copy->timeOffs = self::cloneAll($this->timeOffs);
        $copy->appointments = self::cloneAll($this->appointments);
        $copy->waitlist = self::cloneAll($this->waitlist);

        return $copy;
    }

    /**
     * @template T of object
     * @param array<int, T> $rows
     * @return array<int, T>
     */
    private static function cloneAll(array $rows): array
    {
        return array_map(static fn (object $row): object => clone $row, $rows);
    }

    public function restore(self $snapshot): void
    {
        foreach (get_object_vars($snapshot) as $property => $value) {
            $this->{$property} = $value;
        }
    }
}
