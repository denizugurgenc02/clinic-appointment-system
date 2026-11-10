<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Appointment;
use App\Model\Enum\SlotStatus;
use App\Model\Schedule;
use App\Model\Slot;
use App\Model\TimeOff;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Çekirdek algoritma: çalışma takviminden slot üretimi.
 *
 * Saf sınıftır: dosyaya/veritabanına dokunmaz, "şimdi" dışarıdan verilir.
 * Bu yüzden aynı girdiye her zaman aynı çıktıyı verir ve birim testle kanıtlanır.
 *
 * Kurallar:
 *  - Gün, takvimdeki satırlardan oluşur; öğle arası iki satır arasındaki boşluktur (K2).
 *  - Slot, satırın bitişini aşamaz (K2).
 *  - İzin/istisna aralığıyla kesişen slot üretilmez (K4).
 *  - Başlamış slot Past, aktif randevuyla kesişen slot Booked işaretlenir (K5, K6).
 */
final class SlotGenerator
{
    /**
     * @param list<Schedule>    $schedules   Doktorun haftalık çalışma satırları
     * @param list<TimeOff>     $timeOffs    İzin ve istisnalar
     * @param list<Appointment> $busy        Aktif (Booked) randevular
     * @param DateTimeImmutable $from        Başlangıç günü (saati yok sayılır)
     * @param int               $days        Kaç gün üretilecek (hafta için 7)
     * @param int               $slotMinutes Randevu süresi
     *
     * @return list<Slot> Kronolojik sırada
     */
    public function generate(
        array $schedules,
        array $timeOffs,
        array $busy,
        DateTimeImmutable $from,
        int $days,
        int $slotMinutes,
        DateTimeImmutable $now,
    ): array {
        if ($slotMinutes <= 0) {
            throw new InvalidArgumentException('Slot süresi pozitif olmalı.');
        }

        $step = new DateInterval('PT' . $slotMinutes . 'M');
        $firstDay = $from->setTime(0, 0);
        $dayPeriod = new DatePeriod($firstDay, new DateInterval('P1D'), $firstDay->modify('+' . $days . ' days'));

        $slots = [];

        foreach ($dayPeriod as $day) {
            foreach ($this->rowsFor($schedules, (int) $day->format('N')) as $row) {
                $blockStart = $row->startOn($day);
                $blockEnd = $row->endOn($day);

                foreach (new DatePeriod($blockStart, $step, $blockEnd) as $start) {
                    $end = $start->add($step);

                    // Satırın bitişini aşan slot üretilmez (ör. 11:45 + 30 dk > 12:00)
                    if ($end > $blockEnd) {
                        break;
                    }

                    if ($this->isOnTimeOff($timeOffs, $start, $end)) {
                        continue;
                    }

                    $slots[] = new Slot($start, $end, $this->statusOf($start, $end, $busy, $now));
                }
            }
        }

        return $slots;
    }

    /**
     * O güne ait satırlar, başlangıç saatine göre sıralı.
     *
     * @param list<Schedule> $schedules
     * @return list<Schedule>
     */
    private function rowsFor(array $schedules, int $dayOfWeek): array
    {
        $rows = array_values(array_filter($schedules, static fn (Schedule $s): bool => $s->dayOfWeek === $dayOfWeek));
        usort($rows, static fn (Schedule $a, Schedule $b): int => strcmp($a->startTime, $b->startTime));

        return $rows;
    }

    /**
     * @param list<TimeOff> $timeOffs
     */
    private function isOnTimeOff(array $timeOffs, DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        foreach ($timeOffs as $timeOff) {
            if ($timeOff->overlaps($start, $end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Appointment> $busy
     */
    private function statusOf(DateTimeImmutable $start, DateTimeImmutable $end, array $busy, DateTimeImmutable $now): SlotStatus
    {
        if ($start <= $now) {
            return SlotStatus::Past;
        }

        foreach ($busy as $appointment) {
            if ($appointment->status->isActive() && $appointment->overlaps($start, $end)) {
                return SlotStatus::Booked;
            }
        }

        return SlotStatus::Available;
    }
}
