<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Model\Doctor;
use App\Model\Enum\SlotStatus;
use App\Model\Slot;
use App\Repository\Contract\AppointmentRepositoryInterface;
use App\Repository\Contract\ScheduleRepositoryInterface;
use App\Repository\Contract\TimeOffRepositoryInterface;
use App\Service\Exception\DomainRuleException;
use App\Service\Exception\SlotAlreadyTakenException;
use DateInterval;
use DateTimeImmutable;

/**
 * Repository'lerden takvim/izin/randevu verisini toplayıp saf SlotGenerator'a verir.
 */
final class SlotService
{
    public function __construct(
        private readonly SlotGenerator $generator,
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly TimeOffRepositoryInterface $timeOffs,
        private readonly AppointmentRepositoryInterface $appointments,
        private readonly ClockInterface $clock,
        /** Kaç hafta ilerisine randevu verilir */
        private readonly int $horizonWeeks = 4,
    ) {
    }

    /**
     * Verilen tarihin haftasının pazartesi 00:00'ı.
     */
    public function weekStart(DateTimeImmutable $date): DateTimeImmutable
    {
        $offset = (int) $date->format('N') - 1;

        return $date->setTime(0, 0)->sub(new DateInterval('P' . $offset . 'D'));
    }

    public function currentWeekStart(): DateTimeImmutable
    {
        return $this->weekStart($this->clock->now());
    }

    /**
     * Haftalık ızgara: tarih (Y-m-d) => o günün slotları. Randevu ufku dışındaki slotlar çıkarılır.
     *
     * @return array<string, list<Slot>>
     */
    public function weekGrid(Doctor $doctor, DateTimeImmutable $anyDayOfWeek): array
    {
        $monday = $this->weekStart($anyDayOfWeek);
        $horizonEnd = $this->horizonEnd();

        $grid = [];
        for ($i = 0; $i < 7; $i++) {
            $grid[$monday->modify('+' . $i . ' days')->format('Y-m-d')] = [];
        }

        foreach ($this->slotsFor($doctor, $monday, 7) as $slot) {
            if ($slot->start < $horizonEnd) {
                $grid[$slot->start->format('Y-m-d')][] = $slot;
            }
        }

        return $grid;
    }

    /**
     * Önceki/sonraki haftaya gidilebilir mi? (geçmiş haftalar ve ufuk dışı kapalı)
     *
     * @return array{prev: ?DateTimeImmutable, next: ?DateTimeImmutable}
     */
    public function weekNavigation(DateTimeImmutable $anyDayOfWeek): array
    {
        $monday = $this->weekStart($anyDayOfWeek);
        $prev = $monday->modify('-7 days');
        $next = $monday->modify('+7 days');

        return [
            'prev' => $prev >= $this->currentWeekStart() ? $prev : null,
            'next' => $next < $this->horizonEnd() ? $next : null,
        ];
    }

    /**
     * Slot ızgarada görünüyor ve boşsa döndürür; değilse nedenini anlatan istisna fırlatır.
     * Transaction içinde çağrılır; böylece "dolu mu" kontrolü kilit altında yapılır.
     */
    public function bookableSlot(Doctor $doctor, DateTimeImmutable $start): Slot
    {
        if ($start >= $this->horizonEnd()) {
            throw new DomainRuleException(sprintf('En fazla %d hafta sonrasına randevu alınabilir.', $this->horizonWeeks));
        }

        $slot = $this->findSlot($doctor, $start);

        if ($slot === null) {
            throw new DomainRuleException('Seçilen saat doktorun çalışma takviminde yok veya doktor o saatte izinli.');
        }

        return match ($slot->status) {
            SlotStatus::Available => $slot,
            SlotStatus::Booked => throw new SlotAlreadyTakenException('Seçtiğiniz saat az önce doldu. Lütfen başka bir saat seçin.'),
            SlotStatus::Past => throw new DomainRuleException('Geçmiş bir saate randevu alınamaz.'),
        };
    }

    /**
     * Takvimde bu başlangıç saatine sahip slot (durumu ne olursa olsun).
     */
    public function findSlot(Doctor $doctor, DateTimeImmutable $start): ?Slot
    {
        foreach ($this->slotsFor($doctor, $start, 1) as $slot) {
            if ($slot->start == $start) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Rapor için: doktorun o günkü toplam slot kapasitesi (izinler düşülmüş).
     */
    public function dayCapacity(Doctor $doctor, DateTimeImmutable $day): int
    {
        return count($this->slotsFor($doctor, $day, 1));
    }

    /**
     * @return list<Slot>
     */
    private function slotsFor(Doctor $doctor, DateTimeImmutable $from, int $days): array
    {
        $doctorId = $doctor->id();
        $start = $from->setTime(0, 0);
        $end = $start->modify('+' . $days . ' days');

        return $this->generator->generate(
            $this->schedules->forDoctor($doctorId),
            $this->timeOffs->forDoctor($doctorId, $start, $end),
            $this->appointments->activeForDoctorBetween($doctorId, $start, $end),
            $start,
            $days,
            $doctor->slotMinutes,
            $this->clock->now(),
        );
    }

    private function horizonEnd(): DateTimeImmutable
    {
        return $this->clock->now()->setTime(0, 0)->modify('+' . ($this->horizonWeeks * 7) . ' days');
    }
}
