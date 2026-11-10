<?php

declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Izgaradaki bir slotun durumu (kaydedilmez, anlık hesaplanır).
 */
enum SlotStatus: string
{
    case Available = 'available';
    case Booked = 'booked';
    case Past = 'past';
}
