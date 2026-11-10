<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Model\Appointment;
use App\Model\Enum\SlotStatus;
use App\Model\Schedule;
use App\Model\Slot;
use App\Model\TimeOff;
use App\Service\SlotGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Çekirdek problem 1: çalışma takviminden slot üretimi.
 * 2026-11-16 Pazartesi'dir.
 */
final class SlotGeneratorTest extends TestCase
{
    private const MONDAY = '2026-11-16';

    private SlotGenerator $generator;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->generator = new SlotGenerator();
        // "Şimdi" test haftasından önce: hiçbir slot geçmişte değil
        $this->now = new DateTimeImmutable('2026-11-10 08:00');
    }

    /**
     * Pazartesi 09:00-12:00 ve 13:00-17:00 (öğle arası 12:00-13:00).
     *
     * @return list<Schedule>
     */
    private function mondayWithLunchBreak(): array
    {
        return [
            new Schedule(1, 7, 1, '09:00', '12:00'),
            new Schedule(2, 7, 1, '13:00', '17:00'),
        ];
    }

    /**
     * @param list<Slot> $slots
     * @return list<string>
     */
    private static function times(array $slots): array
    {
        return array_map(static fn (Slot $s): string => $s->start->format('H:i'), $slots);
    }

    private function day(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date);
    }

    public function testLunchBreakIsSkipped(): void
    {
        $slots = $this->generator->generate($this->mondayWithLunchBreak(), [], [], $this->day(self::MONDAY), 1, 30, $this->now);
        $times = self::times($slots);

        self::assertContains('11:30', $times, 'Öğleden önceki son slot');
        self::assertNotContains('12:00', $times, 'Öğle arasında slot üretilmemeli');
        self::assertNotContains('12:30', $times, 'Öğle arasında slot üretilmemeli');
        self::assertContains('13:00', $times, 'Öğleden sonraki ilk slot');
        self::assertCount(6 + 8, $slots, '3 saat + 4 saat, 30 dakikalık slotlar');
    }

    public function testSlotNeverCrossesTheEndOfAWorkingBlock(): void
    {
        // Sınır durumu: 3 saatlik satır 40 dk'ya tam bölünmez.
        // 09:00, 09:40, 10:20, 11:00 sığar; 11:40 başlayan slot 12:20'de biterdi -> üretilmemeli.
        // (İlk sürümde 45 dk kullanılmıştı; 180/45 tam bölündüğü için kural bozulunca test kırılmıyordu.)
        $slots = $this->generator->generate($this->mondayWithLunchBreak(), [], [], $this->day(self::MONDAY), 1, 40, $this->now);
        $morning = array_values(array_filter($slots, static fn (Slot $s): bool => $s->start->format('H') < '12'));

        self::assertSame(['09:00', '09:40', '10:20', '11:00'], self::times($morning));
        self::assertNotContains('11:40', self::times($slots), 'Öğle arasına taşan slot üretilmemeli');
        foreach ($slots as $slot) {
            self::assertFalse(
                $slot->start < $this->day(self::MONDAY . ' 12:00') && $slot->end > $this->day(self::MONDAY . ' 12:00'),
                'Hiçbir slot öğle arasına taşmamalı',
            );
        }
    }

    public function testNoSlotsOnAFullDayTimeOff(): void
    {
        $timeOff = new TimeOff(1, 7, $this->day(self::MONDAY . ' 00:00'), $this->day('2026-11-17 00:00'), 'Yıllık izin');

        $slots = $this->generator->generate($this->mondayWithLunchBreak(), [$timeOff], [], $this->day(self::MONDAY), 1, 30, $this->now);

        self::assertSame([], $slots, 'İzin gününde slot üretilmemeli');
    }

    public function testPartialDayExceptionRemovesOnlyOverlappingSlots(): void
    {
        $meeting = new TimeOff(1, 7, $this->day(self::MONDAY . ' 14:00'), $this->day(self::MONDAY . ' 15:30'), 'Kurul toplantısı');

        $times = self::times($this->generator->generate($this->mondayWithLunchBreak(), [$meeting], [], $this->day(self::MONDAY), 1, 30, $this->now));

        self::assertContains('13:30', $times);
        self::assertNotContains('14:00', $times);
        self::assertNotContains('15:00', $times);
        self::assertContains('15:30', $times);
    }

    public function testDaysWithoutScheduleProduceNoSlots(): void
    {
        // Pazartesi'den başlayan 7 günlük hafta; yalnızca Pazartesi çalışılıyor
        $slots = $this->generator->generate($this->mondayWithLunchBreak(), [], [], $this->day(self::MONDAY), 7, 30, $this->now);

        $days = array_unique(array_map(static fn (Slot $s): string => $s->start->format('Y-m-d'), $slots));

        self::assertSame([self::MONDAY], array_values($days));
    }

    public function testBookedAndPastSlotsAreMarked(): void
    {
        $now = $this->day(self::MONDAY . ' 10:10');
        $booked = Appointment::book(3, 7, $this->day(self::MONDAY . ' 11:00'), $this->day(self::MONDAY . ' 11:30'), null, $now);

        $slots = $this->generator->generate($this->mondayWithLunchBreak(), [], [$booked], $this->day(self::MONDAY), 1, 30, $now);
        $status = [];
        foreach ($slots as $slot) {
            $status[$slot->start->format('H:i')] = $slot->status;
        }

        self::assertSame(SlotStatus::Past, $status['10:00'], 'Başlamış slot alınamaz (K5)');
        self::assertSame(SlotStatus::Available, $status['10:30']);
        self::assertSame(SlotStatus::Booked, $status['11:00']);
    }

    public function testSlotsAreSortedChronologicallyEvenIfScheduleRowsAreNot(): void
    {
        $rows = array_reverse($this->mondayWithLunchBreak());

        $times = self::times($this->generator->generate($rows, [], [], $this->day(self::MONDAY), 1, 60, $this->now));

        self::assertSame(['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'], $times);
    }
}
