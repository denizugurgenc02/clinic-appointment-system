<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Clock\ClockInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\View\View;
use App\Model\Enum\AppointmentStatus;
use App\Service\AppointmentService;
use App\Service\AuthService;
use App\Service\Exception\ValidationException;
use DomainException;

/**
 * Doktor paneli: günlük randevu listesi ve "Tamamlandı / Gelmedi" işaretleme.
 */
final class DoctorPanelController extends AbstractController
{
    public function __construct(
        View $view,
        Session $session,
        AuthService $auth,
        private readonly AppointmentService $appointments,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function index(Request $request): Response
    {
        $now = $this->clock->now();
        $date = self::toDate($request->query('date'), 'Y-m-d') ?? $now->setTime(0, 0);
        $rows = $this->appointments->forDoctorOn($this->user()->id(), $date);

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row->appointment->status->value] = ($counts[$row->appointment->status->value] ?? 0) + 1;
        }

        return $this->render('doctor/index', [
            'title' => 'Günlük Randevu Listesi',
            'date' => $date,
            'rows' => $rows,
            'counts' => $counts,
            'now' => $now,
        ]);
    }

    public function updateStatus(Request $request): Response
    {
        try {
            $status = AppointmentStatus::tryFrom((string) $request->input('status'))
                ?? throw new ValidationException(['status' => 'Geçersiz durum.']);

            $appointment = $this->appointments->markOutcome(self::requireInt($request->param('id'), 'id'), $status, $this->user());
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/doctor');
        }

        $this->success(sprintf('%s randevusu "%s" olarak işaretlendi.', $appointment->startsAt->format('H:i'), $appointment->status->label()));

        return $this->back($request, '/doctor');
    }
}
