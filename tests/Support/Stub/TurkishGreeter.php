<?php

declare(strict_types=1);

namespace Tests\Support\Stub;

final class TurkishGreeter implements GreeterInterface
{
    public function greet(): string
    {
        return 'Merhaba';
    }
}
