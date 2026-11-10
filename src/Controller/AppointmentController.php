<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Clock\ClockInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\View\View;
use App\Model\Enum\UserRole;
use App\Service\AppointmentService;
use App\Service\AuthService;
use App\Service\ClinicDirectoryService;
use App\Service\WaitlistService;
use DomainException;

/**
 * Hastanın randevu ekranları: randevu al, randevularım, tarih değiştir, iptal.
 */
final class AppointmentController extends AbstractController
{
    public const SLOT_FORMAT = 'Y-m-d\TH:i';

    public function __construct(
        View $view,
        Session $session,
        AuthService $auth,
        private readonly AppointmentService $appointments,
        private readonly WaitlistService $waitlist,
        private readonly ClinicDirectoryService $directory,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($view, $session, $auth);
    }

    /**
     * GET /appointments/book — poliklinik/doktor seçimi + AJAX slot ızgarası
     */
    public function create(Request $request): Response
    {
        return $this->render('patient/book', [
            'title' => 'Randevu Al',
            'departments' => $this->directory->departments(),
            'doctors' => $this->directory->doctors(),
            'selectedDoctorId' => self::toInt($request->query('doctor_id')),
        ]);
    }

    /**
     * POST /appointments
     */
    public function store(Request $request): Response
    {
        try {
            $appointment = $this->appointments->book(
                $this->user()->id(),
                self::requireInt($request->input('doctor_id'), 'doctor_id'),
                self::requireDate($request->input('starts_at'), self::SLOT_FORMAT, 'starts_at'),
                $request->input('note'),
            );
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/appointments/book');
        }

        $this->success('Randevunuz oluşturuldu: ' . $appointment->startsAt->format('d.m.Y H:i'));

        return $this->redirect('/appointments');
    }

    /**
     * GET /appointments — yalnızca kendi randevuları (K8) ve bekleme listesi kayıtları
     */
    public function index(Request $request): Response
    {
        $user = $this->user();
        $now = $this->clock->now();
        $upcoming = [];
        $past = [];

        foreach ($this->appointments->forPatient($user->id()) as $details) {
            if ($details->appointment->status->isActive() && $details->appointment->startsAt > $now) {
                $upcoming[] = $details;
            } else {
                $past[] = $details;
            }
        }

        return $this->render('patient/appointments', [
            'title' => 'Randevularım',
            'upcoming' => $upcoming,
            'past' => array_reverse($past),
            'waitlist' => $this->waitlist->forPatient($user->id()),
            'now' => $now,
        ]);
    }

    /**
     * GET /appointments/{id}/reschedule
     */
    public function edit(Request $request): Response
    {
        try {
            $details = $this->appointments->detailsFor(self::requireInt($request->param('id'), 'id'), $this->user());
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/appointments');
        }

        if (!$details->appointment->canBeCancelled($this->clock->now())) {
            $this->session->flash('warning', 'Bu randevunun tarihi artık değiştirilemez.');

            return $this->redirect('/appointments');
        }

        return $this->render('patient/reschedule', [
            'title' => 'Randevu Tarihini Değiştir',
            'details' => $details,
        ]);
    }

    /**
     * POST /appointments/{id}/reschedule
     */
    public function reschedule(Request $request): Response
    {
        try {
            $new = $this->appointments->reschedule(
                self::requireInt($request->param('id'), 'id'),
                self::requireDate($request->input('starts_at'), self::SLOT_FORMAT, 'starts_at'),
                $this->user(),
            );
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/appointments');
        }

        $this->success('Randevunuz ' . $new->startsAt->format('d.m.Y H:i') . ' olarak güncellendi.');

        return $this->redirect($this->user()->is(UserRole::Secretary) ? '/secretary' : '/appointments');
    }

    /**
     * POST /appointments/{id}/cancel — hasta (yalnızca kendi) veya sekreter
     */
    public function cancel(Request $request): Response
    {
        $fallback = $this->user()->role->homePath();

        try {
            $this->appointments->cancel(self::requireInt($request->param('id'), 'id'), $this->user());
        } catch (DomainException $e) {
            return $this->failed($request, $e, $fallback);
        }

        $this->success('Randevu iptal edildi.');

        return $this->back($request, $fallback);
    }
}
