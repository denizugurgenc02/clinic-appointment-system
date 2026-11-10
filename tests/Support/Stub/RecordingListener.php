<?php

declare(strict_types=1);

namespace Tests\Support\Stub;

use App\Core\Event\EventListenerInterface;

final class RecordingListener implements EventListenerInterface
{
    /** @var list<object> */
    public array $received = [];

    public function handle(object $event): void
    {
        $this->received[] = $event;
    }
}
