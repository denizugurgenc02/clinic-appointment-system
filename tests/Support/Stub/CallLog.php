<?php

declare(strict_types=1);

namespace Tests\Support\Stub;

/**
 * Middleware çalışma sırasını kaydeder.
 */
final class CallLog
{
    /** @var list<string> */
    public array $calls = [];
}
