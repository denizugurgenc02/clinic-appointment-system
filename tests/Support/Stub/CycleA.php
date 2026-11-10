<?php

declare(strict_types=1);

namespace Tests\Support\Stub;

/**
 * CycleA <-> CycleB: Container'ın döngüsel bağımlılığı yakaladığını sınamak için.
 */
final class CycleA
{
    public function __construct(public readonly CycleB $b)
    {
    }
}
