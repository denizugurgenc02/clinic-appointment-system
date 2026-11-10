<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Model\AppointmentFilter;
use App\Model\Enum\AppointmentStatus;
use App\Repository\Contract\AppointmentRepositoryInterface;
use Tests\Support\AppTestCase;

/**
 * Çekirdek problem 2 kanıtı: iki eşzamanlı randevu isteğinden yalnızca biri kabul edilir.
 *
 * İki AYRI PHP süreci (iki ayrı HTTP isteği gibi) aynı anda aynı doktorun aynı slotunu ister.
 * Kayıt adımı yapay olarak 300 ms geciktirilir; kilit olmasaydı ikisi de "boş" görüp kaydederdi.
 */
final class ConcurrentBookingTest extends AppTestCase
{
    private const SLOT = '2026-11-17 10:00';

    /**
     * @return list<string> Her sürecin çıktısı
     */
    private function bookSimultaneously(int $doctorId, bool $useLock): array
    {
        $script = self::rootPath() . '/tests/Support/book-slot.php';
        $startAt = sprintf('%.6F', microtime(true) + 1.0);
        $processes = [];

        foreach ([$this->patient('Ayşe')->id(), $this->patient('Mehmet')->id()] as $patientId) {
            $command = [PHP_BINARY, $script, $this->dataDir, (string) $patientId, (string) $doctorId, self::SLOT, $startAt, $useLock ? '1' : '0'];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $processes[] = [$process, $pipes];
        }

        $outputs = [];
        foreach ($processes as [$process, $pipes]) {
            $outputs[] = trim((string) stream_get_contents($pipes[1])) . trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }

        sort($outputs);

        return $outputs;
    }

    private function activeAppointmentsInSlot(int $doctorId): int
    {
        $all = $this->get(AppointmentRepositoryInterface::class)->search(new AppointmentFilter(doctorId: $doctorId));

        return count(array_filter(
            $all,
            static fn ($d): bool => $d->appointment->status === AppointmentStatus::Booked
                && $d->appointment->startsAt->format('Y-m-d H:i') === self::SLOT,
        ));
    }

    public function testOnlyOneOfTwoSimultaneousRequestsIsAccepted(): void
    {
        $doctor = $this->doctor();

        $outputs = $this->bookSimultaneously($doctor->id(), useLock: true);

        self::assertSame(['BOOKED', 'TAKEN'], $outputs, 'Biri kabul, diğeri "slot dolu" almalı');
        self::assertSame(1, $this->activeAppointmentsInSlot($doctor->id()), 'Slotta tek aktif randevu olmalı');
    }

    /**
     * Karşılaştırma: kilit kaldırıldığında aynı senaryo çift randevuya yol açar.
     * Bu test, yukarıdaki testin kilidi gerçekten sınadığını gösterir.
     */
    public function testWithoutLockingBothRequestsWouldSucceed(): void
    {
        $doctor = $this->doctor();

        $outputs = $this->bookSimultaneously($doctor->id(), useLock: false);

        self::assertSame(['BOOKED', 'BOOKED'], $outputs);
        self::assertSame(2, $this->activeAppointmentsInSlot($doctor->id()), 'Kilitsiz: çift randevu oluşur');
    }
}
