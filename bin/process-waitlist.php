<?php

declare(strict_types=1);

/*
 * Süresi dolan bekleme listesi tekliflerini işler ve slotu sıradaki hastaya teklif eder.
 * Uygulama bunu zaten istek anında yapar (cron gerekmez); bu betik demo ve elle çalıştırma içindir.
 *
 *   docker compose exec app php bin/process-waitlist.php
 */

use App\Core\Application;
use App\Service\WaitlistService;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new Application(dirname(__DIR__));
$count = $app->container()->get(WaitlistService::class)->expireStale();

printf("%d süresi dolmuş teklif işlendi.\n", $count);
