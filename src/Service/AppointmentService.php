<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Core\Event\EventDispatcher;
use App\Model\Appointment;
use App\Model\AppointmentDetails;
use App\Model\AppointmentFilter;
use App\Model\Enum\AppointmentStatus;
use App\Model\Enum\UserRole;
use App\Model\Event\AppointmentCancelled;
use App\Model\User;
use App\Repository\Contract\AppointmentRepositoryInterface;
use App\Repository\Contract\DoctorRepositoryInterface;
use App\Repository\Contract\PatientRepositoryInterface;
use App\Repository\Contract\TransactionManagerInterface;
use App\Service\Exception\AccessDeniedException;
use App\Service\Exception\DomainRuleException;
use App\Service\Exception\NotFoundException;
use DateTimeImmutable;

/**
 * Randevu alma, iptal, tarih değiştirme ve doktorun sonuç işaretlemesi.
 *
 * Çakışma önleme (çekirdek problem 2): "slot boş mu?" kontrolü ile kayıt aynı
 * transaction içinde yapılır. Aşama 2'de transaction bir dosya kilidi olduğu için
 * aynı anda gelen ikinci istek, birincisi bitene kadar bekler ve slotu dolu görür.
 *
 * İptal olayı (Observer): iptal kaydedildikten SONRA AppointmentCancelled yayınlanır;
 * servis bekleme listesini tanımaz.
 */
final class AppointmentService
{
    private const NOTE_MAX_LENGTH = 500;

    public function __construct(
        private readonly AppointmentRepositoryInterface $appointments,
        private readonly DoctorRepositoryInterface $doctors,
        private readonly PatientRepositoryInterface $patients,
        private readonly SlotService $slots,
        private readonly TransactionManagerInterface $tx,
        private readonly EventDispatcher $events,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param int|null $ignoreAppointmentId Tarih değiştirmede hastanın eski randevusu çakışma sayılmaz
     */
    public function book(
        int $patientId,
        int $doctorId,
        DateTimeImmutable $start,
        ?string $note = null,
        ?int $ignoreAppointmentId = null,
    ): Appointment {
        $note = $this->normalizeNote($note);

        return $this->tx->run(function () use ($patientId, $doctorId, $start, $note, $ignoreAppointmentId): Appointment {
            $doctor = $this->doctors->find($doctorId) ?? throw new NotFoundException('Doktor bulunamadı.');
            $this->patients->find($patientId) ?? throw new NotFoundException('Hasta bulunamadı.');

            // K5, K6: slot takvimde var, gelecekte ve boş olmalı (kilit altında okunur)
            $slot = $this->slots->bookableSlot($doctor, $start);

            // K7: hastanın aynı saatte başka aktif randevusu olamaz
            foreach ($this->appointments->activeForPatientBetween($patientId, $slot->start, $slot->end) as $other) {
                if ($other->id !== $ignoreAppointmentId) {
                    throw new DomainRuleException('Bu saatte zaten başka bir randevunuz var.');
                }
            }

            $appointment = Appointment::book($patientId, $doctorId, $slot->start, $slot->end, $note, $this->clock->now());

            return $this->appointments->save($appointment);
        });
    }

    public function cancel(int $appointmentId, User $actor): Appointment
    {
        $appointment = $this->tx->run(function () use ($appointmentId, $actor): Appointment {
            $appointment = $this->findManageable($appointmentId, $actor);
            $appointment->cancel($this->clock->now());

            return $this->appointments->save($appointment);
        });

        // Commit'ten sonra: dinleyiciler (bekleme listesi) kendi işlemlerini açar
        $this->events->dispatch(new AppointmentCancelled(
            $appointment->id(),
            $appointment->doctorId,
            $appointment->startsAt,
            $appointment->endsAt,
        ));

        return $appointment;
    }

    /**
     * Yeni slotu alır ve eskisini iptal eder; ikisi tek transaction'dadır.
     */
    public function reschedule(int $appointmentId, DateTimeImmutable $newStart, User $actor): Appointment
    {
        [$new, $old] = $this->tx->run(function () use ($appointmentId, $newStart, $actor): array {
            $old = $this->findManageable($appointmentId, $actor);
            $now = $this->clock->now();

            if (!$old->canBeCancelled($now)) {
                throw new DomainRuleException('Yalnızca aktif ve henüz başlamamış randevuların tarihi değiştirilebilir.');
            }

            if ($old->startsAt == $newStart) {
                throw new DomainRuleException('Yeni saat mevcut randevuyla aynı.');
            }

            $new = $this->book($old->patientId, $old->doctorId, $newStart, $old->patientNote, $old->id);
            $old->cancel($now);
            $this->appointments->save($old);

            return [$new, $old];
        });

        $this->events->dispatch(new AppointmentCancelled($old->id(), $old->doctorId, $old->startsAt, $old->endsAt));

        return $new;
    }

    /**
     * Doktor kendi geçmiş randevusunu Completed / NoShow yapar.
     */
    public function markOutcome(int $appointmentId, AppointmentStatus $outcome, User $doctor): Appointment
    {
        return $this->tx->run(function () use ($appointmentId, $outcome, $doctor): Appointment {
            $appointment = $this->appointments->find($appointmentId) ?? throw new NotFoundException('Randevu bulunamadı.');

            $isOwnAppointment = $doctor->is(UserRole::Doctor) && $appointment->doctorId === $doctor->id;
            if (!$isOwnAppointment && !$doctor->hasFullAccess()) {
                throw new AccessDeniedException('Yalnızca kendi randevularınızı işaretleyebilirsiniz.');
            }

            $appointment->markOutcome($outcome, $this->clock->now());

            return $this->appointments->save($appointment);
        });
    }

    /**
     * Rolüne göre görebileceği tek randevu (K8).
     */
    public function detailsFor(int $appointmentId, User $actor): AppointmentDetails
    {
        $appointment = $this->findManageable($appointmentId, $actor);

        foreach ($this->appointments->search(new AppointmentFilter(patientId: $appointment->patientId)) as $details) {
            if ($details->appointment->id === $appointment->id) {
                return $details;
            }
        }

        throw new NotFoundException('Randevu bulunamadı.');
    }

    /**
     * Hasta yalnızca kendi randevularını görür (K8).
     *
     * @return list<AppointmentDetails>
     */
    public function forPatient(int $patientId): array
    {
        return $this->appointments->search(new AppointmentFilter(patientId: $patientId));
    }

    /**
     * @return list<AppointmentDetails>
     */
    public function forDoctorOn(int $doctorId, DateTimeImmutable $day): array
    {
        return $this->appointments->search(new AppointmentFilter(doctorId: $doctorId, dateFrom: $day, dateTo: $day));
    }

    /**
     * @return list<AppointmentDetails>
     */
    public function search(AppointmentFilter $filter): array
    {
        return $this->appointments->search($filter);
    }

    private function findManageable(int $appointmentId, User $actor): Appointment
    {
        $appointment = $this->appointments->find($appointmentId) ?? throw new NotFoundException('Randevu bulunamadı.');

        $allowed = match ($actor->role) {
            UserRole::Secretary, UserRole::SuperAdmin => true,
            UserRole::Patient => $appointment->patientId === $actor->id,
            UserRole::Doctor => false,
        };

        if (!$allowed) {
            throw new AccessDeniedException('Bu randevu üzerinde işlem yapma yetkiniz yok.');
        }

        return $appointment;
    }

    private function normalizeNote(?string $note): ?string
    {
        $note = trim((string) $note);

        if (mb_strlen($note) > self::NOTE_MAX_LENGTH) {
            throw new DomainRuleException(sprintf('Not en fazla %d karakter olabilir.', self::NOTE_MAX_LENGTH));
        }

        return $note === '' ? null : $note;
    }
}
