<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * Doktorun haftalık çalışma satırı. Bir gün birden fazla satırdan oluşabilir;
 * öğle arası iki satır arasındaki boşluktur (K2): 09:00-12:00 ve 13:00-17:00.
 */
final class Schedule
{
    public const DAY_NAMES = [
        1 => 'Pazartesi',
        2 => 'Salı',
        3 => 'Çarşamba',
        4 => 'Perşembe',
        5 => 'Cuma',
        6 => 'Cumartesi',
        7 => 'Pazar',
    ];

    public function __construct(
        public ?int $id,
        public int $doctorId,
        /** ISO-8601: 1 = Pazartesi ... 7 = Pazar */
        public int $dayOfWeek,
        /** "H:i" */
        public string $startTime,
        /** "H:i" */
        public string $endTime,
    ) {
    }

    /**
     * Satırın verilen gündeki başlangıç anı.
     */
    public function startOn(DateTimeImmutable $day): DateTimeImmutable
    {
        return self::at($day, $this->startTime);
    }

    public function endOn(DateTimeImmutable $day): DateTimeImmutable
    {
        return self::at($day, $this->endTime);
    }

    /**
     * Aynı gündeki iki satır çakışıyor mu? (K3)
     */
    public function overlaps(self $other): bool
    {
        return $this->dayOfWeek === $other->dayOfWeek
            && $this->startTime < $other->endTime
            && $this->endTime > $other->startTime;
    }

    public function dayName(): string
    {
        return self::DAY_NAMES[$this->dayOfWeek] ?? (string) $this->dayOfWeek;
    }

    private static function at(DateTimeImmutable $day, string $time): DateTimeImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $day->setTime($hour, $minute);
    }
}
