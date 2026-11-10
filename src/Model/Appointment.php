<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\AppointmentStatus;
use DateTimeImmutable;
use DomainException;

/**
 * Randevu varlığı. Durum geçişleri yalnızca bu sınıfın metotlarıyla yapılır;
 * kural dışı geçişte DomainException fırlatılır.
 */
final class Appointment
{
    public function __construct(
        public ?int $id,
        public readonly int $patientId,
        public readonly int $doctorId,
        public readonly DateTimeImmutable $startsAt,
        public readonly DateTimeImmutable $endsAt,
        public AppointmentStatus $status,
        public readonly ?string $patientNote,
        public readonly DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $cancelledAt = null,
    ) {
    }

    public static function book(
        int $patientId,
        int $doctorId,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        ?string $note,
        DateTimeImmutable $now,
    ): self {
        return new self(null, $patientId, $doctorId, $startsAt, $endsAt, AppointmentStatus::Booked, $note, $now);
    }

    public function id(): int
    {
        if ($this->id === null) {
            throw new \LogicException('Randevu henüz kaydedilmedi.');
        }

        return $this->id;
    }

    /**
     * K9: yalnızca aktif ve henüz başlamamış randevu iptal edilebilir.
     */
    public function canBeCancelled(DateTimeImmutable $now): bool
    {
        return $this->status === AppointmentStatus::Booked && $this->startsAt > $now;
    }

    public function cancel(DateTimeImmutable $now): void
    {
        if (!$this->canBeCancelled($now)) {
            throw new DomainException('Yalnızca aktif ve henüz başlamamış randevular iptal edilebilir.');
        }

        $this->status = AppointmentStatus::Cancelled;
        $this->cancelledAt = $now;
    }

    /**
     * Doktor, başlangıç saati geçmiş aktif randevuyu Completed veya NoShow yapar.
     */
    public function markOutcome(AppointmentStatus $outcome, DateTimeImmutable $now): void
    {
        if (!in_array($outcome, [AppointmentStatus::Completed, AppointmentStatus::NoShow], true)) {
            throw new DomainException('Randevu yalnızca "Tamamlandı" veya "Gelmedi" olarak işaretlenebilir.');
        }

        if ($this->status !== AppointmentStatus::Booked) {
            throw new DomainException('Bu randevunun durumu artık değiştirilemez.');
        }

        if ($this->startsAt > $now) {
            throw new DomainException('Henüz başlamamış bir randevu işaretlenemez.');
        }

        $this->status = $outcome;
    }

    public function overlaps(DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        return $start < $this->endsAt && $end > $this->startsAt;
    }
}
