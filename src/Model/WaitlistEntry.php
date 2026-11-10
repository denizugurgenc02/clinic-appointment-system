<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\WaitlistStatus;
use DateTimeImmutable;
use DomainException;

/**
 * Bekleme listesi kaydı. Sıra FIFO'dur: createdAt (eşitlikte id) en eski olan önce.
 */
final class WaitlistEntry
{
    public function __construct(
        public ?int $id,
        public readonly int $patientId,
        public readonly int $doctorId,
        /** Boşsa doktorun herhangi bir günü kabul edilir (K11) */
        public readonly ?DateTimeImmutable $desiredDate,
        public WaitlistStatus $status,
        public readonly DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $offeredStartsAt = null,
        public ?DateTimeImmutable $offeredEndsAt = null,
        public ?DateTimeImmutable $offerExpiresAt = null,
    ) {
    }

    public function id(): int
    {
        if ($this->id === null) {
            throw new \LogicException('Bekleme kaydı henüz kaydedilmedi.');
        }

        return $this->id;
    }

    /**
     * Serbest kalan slot bu kaydın tercihine uyuyor mu?
     */
    public function accepts(DateTimeImmutable $slotStart): bool
    {
        return $this->desiredDate === null
            || $this->desiredDate->format('Y-m-d') === $slotStart->format('Y-m-d');
    }

    public function offer(DateTimeImmutable $start, DateTimeImmutable $end, DateTimeImmutable $expiresAt): void
    {
        if ($this->status !== WaitlistStatus::Waiting) {
            throw new DomainException('Yalnızca sırada bekleyen hastaya teklif verilebilir.');
        }

        $this->status = WaitlistStatus::Offered;
        $this->offeredStartsAt = $start;
        $this->offeredEndsAt = $end;
        $this->offerExpiresAt = $expiresAt;
    }

    public function isOfferExpired(DateTimeImmutable $now): bool
    {
        return $this->status === WaitlistStatus::Offered
            && $this->offerExpiresAt !== null
            && $this->offerExpiresAt <= $now;
    }

    public function accept(): void
    {
        $this->assertOffered();
        $this->status = WaitlistStatus::Accepted;
    }

    public function decline(): void
    {
        $this->assertOffered();
        $this->status = WaitlistStatus::Declined;
        $this->clearOffer();
    }

    public function expire(): void
    {
        $this->assertOffered();
        $this->status = WaitlistStatus::Expired;
    }

    /**
     * Teklif edilen slot bu arada başkası tarafından alındıysa hasta sırasını kaybetmez.
     */
    public function returnToQueue(): void
    {
        $this->assertOffered();
        $this->status = WaitlistStatus::Waiting;
        $this->clearOffer();
    }

    /**
     * Hasta sıradan kendi isteğiyle çıkar.
     */
    public function leave(): void
    {
        if (!$this->status->isOpen()) {
            throw new DomainException('Bu kayıt zaten kapanmış.');
        }

        $this->status = WaitlistStatus::Declined;
        $this->clearOffer();
    }

    private function assertOffered(): void
    {
        if ($this->status !== WaitlistStatus::Offered) {
            throw new DomainException('Bu kayıt için geçerli bir teklif yok.');
        }
    }

    private function clearOffer(): void
    {
        $this->offeredStartsAt = null;
        $this->offeredEndsAt = null;
        $this->offerExpiresAt = null;
    }
}
