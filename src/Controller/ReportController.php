<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Clock\ClockInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\View\View;
use App\Service\AuthService;
use App\Service\ClinicDirectoryService;
use App\Service\ReportService;

/**
 * Günlük randevu raporu: liste + toplamlar + oranlar.
 */
final class ReportController extends AbstractController
{
    public function __construct(
        View $view,
        Session $session,
        AuthService $auth,
        private readonly ReportService $reports,
        private readonly ClinicDirectoryService $directory,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function daily(Request $request): Response
    {
        $date = self::toDate($request->query('date'), 'Y-m-d') ?? $this->clock->now()->setTime(0, 0);
        $departmentId = self::toInt($request->query('department_id'));

        return $this->render('reports/daily', [
            'title' => 'Günlük Randevu Raporu',
            'report' => $this->reports->daily($date, $departmentId),
            'departments' => $this->directory->departments(),
            'departmentId' => $departmentId,
        ]);
    }
}
