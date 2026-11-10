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
     * FR4: $noticeMinutes > 0 ise başlangıca en az bu kadar dakika kalmış olmalı (tam sınır dahil).
     */
    public function canBeCancelled(DateTimeImmutable $now, int $noticeMinutes = 0): bool
    {
        $deadline = $this->startsAt->modify('-' . $noticeMinutes . ' minutes');

        return $this->status === AppointmentStatus::Booked && $now < $this->startsAt && $now <= $deadline;
    }

    public function cancel(DateTimeImmutable $now, int $noticeMinutes = 0): void
    {
        if (!$this->canBeCancelled($now, $noticeMinutes)) {
            throw new DomainException($noticeMinutes > 0
                ? sprintf('Randevular başlangıçtan en az %d dakika önce iptal edilebilir veya değiştirilebilir.', $noticeMinutes)
                : 'Yalnızca aktif ve henüz başlamamış randevular iptal edilebilir.');
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
