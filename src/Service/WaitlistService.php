<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Model\Appointment;
use App\Model\Enum\WaitlistStatus;
use App\Model\User;
use App\Model\WaitlistDetails;
use App\Model\WaitlistEntry;
use App\Repository\Contract\AppointmentRepositoryInterface;
use App\Repository\Contract\DoctorRepositoryInterface;
use App\Repository\Contract\TransactionManagerInterface;
use App\Repository\Contract\WaitlistRepositoryInterface;
use App\Service\Exception\AccessDeniedException;
use App\Service\Exception\DomainRuleException;
use App\Service\Exception\NotFoundException;
use App\Service\Exception\SlotAlreadyTakenException;
use DateTimeImmutable;

/**
 * Çekirdek problem 3: bekleme listesi (FIFO kuyruk + süreli teklif).
 *
 * - İptal olayıyla serbest kalan slot, kuyruktaki uygun ilk hastaya teklif edilir (K11).
 * - Teklif 30 dk geçerlidir (K12); süre dolar veya hasta reddederse sıradakine geçilir.
 * - Teklif süresince slot herkese açıktır; kabulde slot hâlâ boş mu diye kilit altında bakılır.
 * - Cron yoktur: ilgili sayfalar açılırken expireStale() süresi dolanları işler.
 */
final class WaitlistService
{
    public function __construct(
        private readonly WaitlistRepositoryInterface $waitlist,
        private readonly DoctorRepositoryInterface $doctors,
        private readonly AppointmentRepositoryInterface $appointments,
        private readonly AppointmentService $appointmentService,
        private readonly SlotService $slots,
        private readonly TransactionManagerInterface $tx,
        private readonly ClockInterface $clock,
        private readonly int $offerMinutes = 30,
    ) {
    }

    public function join(int $patientId, int $doctorId, ?DateTimeImmutable $desiredDate): WaitlistEntry
    {
        $now = $this->clock->now();

        if ($desiredDate !== null && $desiredDate->format('Y-m-d') < $now->format('Y-m-d')) {
            throw new DomainRuleException('Tercih edilen tarih geçmişte olamaz.');
        }

        return $this->tx->run(function () use ($patientId, $doctorId, $desiredDate, $now): WaitlistEntry {
            $this->doctors->find($doctorId) ?? throw new NotFoundException('Doktor bulunamadı.');

            // K10: doktor başına tek açık kayıt
            if ($this->waitlist->openForPatientAndDoctor($patientId, $doctorId) !== null) {
                throw new DomainRuleException('Bu doktor için zaten bekleme listesindesiniz.');
            }

            $entry = new WaitlistEntry(null, $patientId, $doctorId, $desiredDate?->setTime(0, 0), WaitlistStatus::Waiting, $now);

            return $this->waitlist->save($entry);
        });
    }

    /**
     * Serbest kalan slotu kuyruktaki uygun ilk hastaya teklif eder.
     * WaitlistListener tarafından AppointmentCancelled olayında çağrılır.
     */
    public function offerFreedSlot(int $doctorId, DateTimeImmutable $start, DateTimeImmutable $end): ?WaitlistEntry
    {
        return $this->tx->run(function () use ($doctorId, $start, $end): ?WaitlistEntry {
            $now = $this->clock->now();
            $doctor = $this->doctors->find($doctorId);

            if ($doctor === null || $start <= $now) {
                return null;
            }

            // Slot bu arada başkası tarafından alındıysa teklif edilecek bir şey yok
            if ($this->slots->findSlot($doctor, $start)?->isAvailable() !== true) {
                return null;
            }

            // K11: aynı slot için aynı anda tek teklif
            if ($this->waitlist->offeredForSlot($doctorId, $start) !== null) {
                return null;
            }

            foreach ($this->waitlist->waitingQueue($doctorId) as $entry) {
                if (!$entry->accepts($start) || $this->patientIsBusy($entry->patientId, $start, $end)) {
                    continue;
                }

                $expiresAt = min($now->modify('+' . $this->offerMinutes . ' minutes'), $start);
                $entry->offer($start, $end, $expiresAt);

                return $this->waitlist->save($entry);
            }

            return null;
        });
    }

    /**
     * Hasta teklifi kabul eder. Slot bu arada dolduysa kayıt sırasını koruyarak Waiting'e döner.
     */
    public function accept(int $entryId, User $patient): Appointment
    {
        $this->expireStale();

        $appointment = $this->tx->run(function () use ($entryId, $patient): ?Appointment {
            $entry = $this->findOwned($entryId, $patient);

            if ($entry->status !== WaitlistStatus::Offered || $entry->offeredStartsAt === null) {
                throw new DomainRuleException('Bu kayıt için geçerli bir teklif yok (süresi dolmuş olabilir).');
            }

            try {
                $appointment = $this->appointmentService->book($entry->patientId, $entry->doctorId, $entry->offeredStartsAt);
            } catch (SlotAlreadyTakenException) {
                $entry->returnToQueue();
                $this->waitlist->save($entry);

                return null;
            }

            $entry->accept();
            $this->waitlist->save($entry);

            return $appointment;
        });

        if ($appointment === null) {
            throw new SlotAlreadyTakenException('Teklif edilen saat bu arada doldu. Sıranız korunuyor.');
        }

        return $appointment;
    }

    public function decline(int $entryId, User $patient): void
    {
        $offer = $this->tx->run(function () use ($entryId, $patient): array {
            $entry = $this->findOwned($entryId, $patient);
            $offer = [$entry->offeredStartsAt, $entry->offeredEndsAt];
            $entry->decline();
            $this->waitlist->save($entry);

            return $offer;
        });

        $this->offerToNext($entryId, $offer);
    }

    /**
     * Hasta sıradan kendi isteğiyle çıkar; açık teklifi varsa sıradakine geçer.
     */
    public function leave(int $entryId, User $patient): void
    {
        $offer = $this->tx->run(function () use ($entryId, $patient): array {
            $entry = $this->findOwned($entryId, $patient);
            $offer = [$entry->offeredStartsAt, $entry->offeredEndsAt];
            $entry->leave();
            $this->waitlist->save($entry);

            return $offer;
        });

        $this->offerToNext($entryId, $offer);
    }

    /**
     * Süresi dolan teklifleri Expired yapar ve slotu sıradaki hastaya teklif eder.
     *
     * @return int İşlenen teklif sayısı
     */
    public function expireStale(): int
    {
        $expired = $this->tx->run(function (): array {
            $slots = [];
            foreach ($this->waitlist->expiredOffers($this->clock->now()) as $entry) {
                if ($entry->offeredStartsAt !== null && $entry->offeredEndsAt !== null) {
                    $slots[] = [$entry->doctorId, $entry->offeredStartsAt, $entry->offeredEndsAt];
                }
                $entry->expire();
                $this->waitlist->save($entry);
            }

            return $slots;
        });

        foreach ($expired as [$doctorId, $start, $end]) {
            $this->offerFreedSlot($doctorId, $start, $end);
        }

        return count($expired);
    }

    /**
     * @return list<WaitlistDetails>
     */
    public function forPatient(int $patientId): array
    {
        $this->expireStale();

        return $this->waitlist->details($patientId);
    }

    /**
     * @return list<WaitlistDetails>
     */
    public function openEntries(): array
    {
        $this->expireStale();

        return $this->waitlist->details(null, true);
    }

    /**
     * @param array{0: ?DateTimeImmutable, 1: ?DateTimeImmutable} $offer
     */
    private function offerToNext(int $entryId, array $offer): void
    {
        [$start, $end] = $offer;
        $entry = $this->waitlist->find($entryId);

        if ($entry !== null && $start !== null && $end !== null) {
            $this->offerFreedSlot($entry->doctorId, $start, $end);
        }
    }

    private function findOwned(int $entryId, User $patient): WaitlistEntry
    {
        $entry = $this->waitlist->find($entryId) ?? throw new NotFoundException('Bekleme kaydı bulunamadı.');

        if ($entry->patientId !== $patient->id) {
            throw new AccessDeniedException('Bu bekleme kaydı size ait değil.');
        }

        return $entry;
    }

    private function patientIsBusy(int $patientId, DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        return $this->appointments->activeForPatientBetween($patientId, $start, $end) !== [];
    }
}
