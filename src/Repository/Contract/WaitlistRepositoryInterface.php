<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Model\WaitlistDetails;
use App\Model\WaitlistEntry;
use DateTimeImmutable;

interface WaitlistRepositoryInterface
{
    public function find(int $id): ?WaitlistEntry;

    public function save(WaitlistEntry $entry): WaitlistEntry;

    /**
     * Hastanın bu doktor için açık (Waiting/Offered) kaydı (K10).
     */
    public function openForPatientAndDoctor(int $patientId, int $doctorId): ?WaitlistEntry;

    /**
     * Doktorun Waiting durumundaki kayıtları, FIFO sırasıyla (created_at, id).
     *
     * @return list<WaitlistEntry>
     */
    public function waitingQueue(int $doctorId): array;

    /**
     * Bu slot için hâlâ açık bir teklif var mı? (K11: aynı slota tek teklif)
     */
    public function offeredForSlot(int $doctorId, DateTimeImmutable $start): ?WaitlistEntry;

    /**
     * Süresi dolmuş (offer_expires_at <= now) teklifler.
     *
     * @return list<WaitlistEntry>
     */
    public function expiredOffers(DateTimeImmutable $now): array;

    /**
     * Ekranlar için kayıt + hasta + doktor + poliklinik; Waiting kayıtlarda sıra numarası dolu.
     *
     * @return list<WaitlistDetails> En yeni önce
     */
    public function details(?int $patientId = null, bool $onlyOpen = false): array;
}
