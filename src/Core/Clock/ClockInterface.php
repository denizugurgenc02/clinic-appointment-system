<?php

declare(strict_types=1);

namespace App\Core\Clock;

use DateTimeImmutable;

/**
 * "Şimdi" bilgisini dışarıdan almak için arayüz.
 * Servisler new DateTime() yerine bunu kullanır; testlerde FrozenClock ile
 * zaman sabitlenir (ör. "teklif 30 dakika sonra düşer" testi).
 */
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
