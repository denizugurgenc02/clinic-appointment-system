<?php

declare(strict_types=1);

use App\Model\Event\AppointmentCancelled;
use App\Service\WaitlistListener;

/*
 * Observer kayıtları: [Olay::class => [Dinleyici::class, ...]]
 *
 * AppointmentService iptal sonrası yalnızca AppointmentCancelled yayınlar;
 * bekleme listesine teklif gönderme işini WaitlistListener yapar.
 */

return [
    AppointmentCancelled::class => [WaitlistListener::class],
];
