<?php

declare(strict_types=1);

/*
 * Eşzamanlılık testinin alt süreci: ayrı bir PHP süreci olarak bir randevu almaya çalışır.
 *
 * Kullanım: php book-slot.php <veri-klasörü> <hasta-id> <doktor-id> <başlangıç> <başlama-zamanı(unix, ondalıklı)> <kilit 1|0>
 * Çıktı:    BOOKED | TAKEN | ERROR:<mesaj>
 */

use App\Core\Application;
use App\Core\Clock\FrozenClock;
use App\Repository\Contract\AppointmentRepositoryInterface;
use App\Repository\Contract\TransactionManagerInterface;
use App\Repository\Json\JsonAppointmentRepository;
use App\Repository\Json\JsonStore;
use App\Service\AppointmentService;
use App\Service\Exception\SlotAlreadyTakenException;
use Tests\Support\SlowSaveAppointmentRepository;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

[, $dataDir, $patientId, $doctorId, $start, $startAt, $useLock] = $argv + array_fill(0, 7, '');

$app = new Application(dirname(__DIR__, 2), ['paths' => ['data' => $dataDir]], FrozenClock::at('2026-11-16 08:00:00'));
$container = $app->container();

$container->singleton(
    AppointmentRepositoryInterface::class,
    static fn ($c): AppointmentRepositoryInterface => new SlowSaveAppointmentRepository(
        new JsonAppointmentRepository($c->get(JsonStore::class)),
        300_000,
    ),
);

if ($useLock === '0') {
    // Kilitsiz karşılaştırma: işi doğrudan çalıştıran sahte transaction
    $container->singleton(TransactionManagerInterface::class, static fn (): TransactionManagerInterface => new class implements TransactionManagerInterface {
        public function run(Closure $work): mixed
        {
            return $work();
        }
    });
}

// İki süreç aynı anda başlasın
time_sleep_until((float) $startAt);

try {
    $container->get(AppointmentService::class)->book((int) $patientId, (int) $doctorId, new DateTimeImmutable($start));
    echo 'BOOKED';
} catch (SlotAlreadyTakenException) {
    echo 'TAKEN';
} catch (Throwable $e) {
    echo 'ERROR:' . $e->getMessage();
}
