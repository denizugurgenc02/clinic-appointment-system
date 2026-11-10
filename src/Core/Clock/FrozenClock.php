<?php

declare(strict_types=1);

namespace App\Core\Clock;

use DateInterval;
use DateTimeImmutable;

/**
 * Testler (ve demo betikleri) için elle ilerletilebilen saat.
 */
final class FrozenClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public static function at(string $datetime): self
    {
        return new self(new DateTimeImmutable($datetime));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    /**
     * @param string $interval ISO 8601 süre, ör. "PT31M"
     */
    public function advance(string $interval): void
    {
        $this->now = $this->now->add(new DateInterval($interval));
    }
}
