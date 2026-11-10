<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;

final class ClockTest extends TestCase
{
    public function testFrozenClockOnlyMovesWhenAdvanced(): void
    {
        $clock = FrozenClock::at('2026-11-10 09:00:00');

        self::assertSame('2026-11-10 09:00', $clock->now()->format('Y-m-d H:i'));

        $clock->advance('PT31M');

        self::assertSame('2026-11-10 09:31', $clock->now()->format('Y-m-d H:i'));
    }
}
