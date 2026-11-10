<?php

declare(strict_types=1);

namespace Tests\Support\Stub;

/**
 * Container testinde autowiring: arayüz bağımlılığı + varsayılan değerli skaler parametre.
 */
final class Greeting
{
    public function __construct(public readonly GreeterInterface $greeter, public readonly int $times = 1)
    {
    }
}
