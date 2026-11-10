<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Container;

/*
 * DI konteyner bağlamaları.
 *
 * Repository arayüzleri burada somut uygulamaya bağlanır. Aşama 2'de JSON,
 * Aşama 3'te MySQL uygulaması bağlanacak; servisler ve controller'lar değişmez.
 *
 * Örnek (Faz 4'te eklenecek):
 *   $c->singleton(AppointmentRepositoryInterface::class,
 *       fn (Container $c) => new JsonAppointmentRepository($c->get(JsonStore::class)));
 */

return static function (Container $c, Config $config): void {
};
