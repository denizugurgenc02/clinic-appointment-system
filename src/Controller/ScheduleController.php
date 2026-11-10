<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\View\View;
use App\Model\Schedule;
use App\Service\AuthService;
use App\Service\ClinicDirectoryService;
use App\Service\ScheduleService;
use DomainException;

/**
 * Sekreterin doktor çalışma takvimi ve izin yönetimi.
 */
final class ScheduleController extends AbstractController
{
    private const DATETIME_FORMAT = 'Y-m-d\TH:i';

    public function __construct(
        View $view,
        Session $session,
        AuthService $auth,
        private readonly ScheduleService $schedules,
        private readonly ClinicDirectoryService $directory,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function index(Request $request): Response
    {
        $departmentId = self::toInt($request->query('department_id'));

        return $this->render('secretary/doctors', [
            'title' => 'Doktorlar ve Takvimler',
            'doctors' => $this->directory->doctors($departmentId),
            'departments' => $this->directory->departments(),
            'departmentId' => $departmentId,
        ]);
    }

    public function show(Request $request): Response
    {
        try {
            $doctor = $this->directory->doctor(self::requireInt($request->param('id'), 'id'));
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/secretary/doctors');
        }

        return $this->render('secretary/doctor', [
            'title' => $doctor->displayName() . ' - Takvim',
            'doctor' => $doctor,
            'rows' => $this->schedules->schedules($doctor->id()),
            'timeOffs' => $this->schedules->upcomingTimeOffs($doctor->id()),
            'dayNames' => Schedule::DAY_NAMES,
        ]);
    }

    public function addRow(Request $request): Response
    {
        $doctorId = (int) self::toInt($request->param('id'));

        try {
            $row = $this->schedules->addRow(
                $doctorId,
                self::requireInt($request->input('day_of_week'), 'day_of_week'),
                (string) $request->input('start_time'),
                (string) $request->input('end_time'),
            );
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/secretary/doctors/' . $doctorId);
        }

        $this->success(sprintf('%s %s-%s çalışma satırı eklendi.', $row->dayName(), $row->startTime, $row->endTime));

        return $this->redirect('/secretary/doctors/' . $doctorId);
    }

    public function deleteRow(Request $request): Response
    {
        try {
            $row = $this->schedules->deleteRow(self::requireInt($request->param('id'), 'id'));
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/secretary/doctors');
        }

        $this->success('Çalışma satırı silindi. Mevcut randevular etkilenmez.');

        return $this->redirect('/secretary/doctors/' . $row->doctorId);
    }

    public function addTimeOff(Request $request): Response
    {
        $doctorId = (int) self::toInt($request->param('id'));

        try {
            $result = $this->schedules->addTimeOff(
                $doctorId,
                self::requireDate($request->input('starts_at'), self::DATETIME_FORMAT, 'starts_at'),
                self::requireDate($request->input('ends_at'), self::DATETIME_FORMAT, 'ends_at'),
                (string) $request->input('reason'),
            );
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/secretary/doctors/' . $doctorId);
        }

        $this->success('İzin eklendi; bu aralıkta yeni slot üretilmeyecek.');

        $conflicts = count($result['conflicts']);
        if ($conflicts > 0) {
            $this->session->flash('warning', sprintf(
                'Bu aralıkta %d aktif randevu var. Randevu Yönetimi ekranından iptal edebilirsiniz.',
                $conflicts,
            ));
        }

        return $this->redirect('/secretary/doctors/' . $doctorId);
    }

    public function deleteTimeOff(Request $request): Response
    {
        try {
            $timeOff = $this->schedules->deleteTimeOff(self::requireInt($request->param('id'), 'id'));
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/secretary/doctors');
        }

        $this->success('İzin kaydı silindi.');

        return $this->redirect('/secretary/doctors/' . $timeOff->doctorId);
    }
}
