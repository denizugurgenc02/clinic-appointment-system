<?php

declare(strict_types=1);

namespace App\Core\Clock;

use DateTimeImmutable;
use DateTimeZone;

final class SystemClock implements ClockInterface
{
    public function __construct(private readonly DateTimeZone $timezone)
    {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
