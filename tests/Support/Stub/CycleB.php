<?php

declare(strict_types=1);

namespace Tests\Support\Stub;

final class CycleB
{
    public function __construct(public readonly CycleA $a)
    {
    }
}
