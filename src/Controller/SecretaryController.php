<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Clock\ClockInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\View\View;
use App\Model\AppointmentFilter;
use App\Model\Enum\AppointmentStatus;
use App\Service\AppointmentService;
use App\Service\AuthService;
use App\Service\ClinicDirectoryService;
use App\Service\Exception\NotFoundException;
use App\Service\WaitlistService;
use DomainException;

/**
 * Sekreter paneli: randevu arama/filtreleme, hasta adına randevu, bekleme listesi izleme.
 */
final class SecretaryController extends AbstractController
{
    public function __construct(
        View $view,
        Session $session,
        AuthService $auth,
        private readonly AppointmentService $appointments,
        private readonly ClinicDirectoryService $directory,
        private readonly WaitlistService $waitlist,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($view, $session, $auth);
    }

    /**
     * GET /secretary — arama (hasta adı/e-posta) + filtreler (poliklinik, doktor, durum, tarih aralığı)
     */
    public function index(Request $request): Response
    {
        $today = $this->clock->now()->setTime(0, 0);
        $hasQuery = $request->query('filter') !== null;

        $filters = [
            'q' => trim((string) $request->query('q')),
            'department_id' => self::toInt($request->query('department_id')),
            'doctor_id' => self::toInt($request->query('doctor_id')),
            'status' => AppointmentStatus::tryFrom((string) $request->query('status')),
            // Varsayılan görünüm: bugünden itibaren 7 gün
            'date_from' => $hasQuery ? self::toDate($request->query('date_from'), 'Y-m-d') : $today,
            'date_to' => $hasQuery ? self::toDate($request->query('date_to'), 'Y-m-d') : $today->modify('+6 days'),
        ];

        $rows = $this->appointments->search(new AppointmentFilter(
            term: $filters['q'] === '' ? null : $filters['q'],
            doctorId: $filters['doctor_id'],
            departmentId: $filters['department_id'],
            status: $filters['status'],
            dateFrom: $filters['date_from'],
            dateTo: $filters['date_to'],
        ));

        return $this->render('secretary/appointments', [
            'title' => 'Randevu Yönetimi',
            'rows' => $rows,
            'filters' => $filters,
            'departments' => $this->directory->departments(),
            'doctors' => $this->directory->doctors(),
            'statuses' => AppointmentStatus::cases(),
            'now' => $this->clock->now(),
        ]);
    }

    /**
     * GET /secretary/appointments/new?patient_q=...&patient_id=...
     */
    public function create(Request $request): Response
    {
        $patientId = self::toInt($request->query('patient_id'));
        $patient = null;

        if ($patientId !== null) {
            try {
                $patient = $this->directory->patient($patientId);
            } catch (NotFoundException) {
                $this->session->flash('warning', 'Hasta bulunamadı.');
            }
        }

        $term = trim((string) $request->query('patient_q'));

        return $this->render('secretary/book', [
            'title' => 'Hasta Adına Randevu',
            'patient' => $patient,
            'patientQuery' => $term,
            'patients' => $patient === null ? $this->directory->searchPatients($term) : [],
            'departments' => $this->directory->departments(),
            'doctors' => $this->directory->doctors(),
        ]);
    }

    /**
     * POST /secretary/appointments
     */
    public function store(Request $request): Response
    {
        try {
            $patientId = self::requireInt($request->input('patient_id'), 'patient_id');
            $appointment = $this->appointments->book(
                $patientId,
                self::requireInt($request->input('doctor_id'), 'doctor_id'),
                self::requireDate($request->input('starts_at'), AppointmentController::SLOT_FORMAT, 'starts_at'),
                $request->input('note'),
            );
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/secretary/appointments/new');
        }

        $this->success('Randevu oluşturuldu: ' . $appointment->startsAt->format('d.m.Y H:i'));

        return $this->redirect('/secretary');
    }

    /**
     * GET /secretary/waitlist
     */
    public function waitlist(Request $request): Response
    {
        return $this->render('secretary/waitlist', [
            'title' => 'Bekleme Listesi',
            'entries' => $this->waitlist->openEntries(),
            'now' => $this->clock->now(),
        ]);
    }
}
