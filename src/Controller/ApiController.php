<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Exception\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\View\View;
use App\Model\Doctor;
use App\Model\Schedule;
use App\Model\Slot;
use App\Service\AuthService;
use App\Service\ClinicDirectoryService;
use App\Service\Exception\NotFoundException;
use App\Service\SlotService;
use App\Service\WaitlistService;

/**
 * AJAX uç noktaları: JSON döndürür, sayfa yenilenmeden slot ızgarası ve doktor listesi yüklenir.
 */
final class ApiController extends AbstractController
{
    private const DAY_SHORT = [1 => 'Pzt', 2 => 'Sal', 3 => 'Çar', 4 => 'Per', 5 => 'Cum', 6 => 'Cmt', 7 => 'Paz'];

    public function __construct(
        View $view,
        Session $session,
        AuthService $auth,
        private readonly ClinicDirectoryService $directory,
        private readonly SlotService $slots,
        private readonly WaitlistService $waitlist,
    ) {
        parent::__construct($view, $session, $auth);
    }

    /**
     * GET /api/doctors?department_id=3
     */
    public function doctors(Request $request): Response
    {
        $doctors = $this->directory->doctors(self::toInt($request->query('department_id')));

        return Response::json([
            'doctors' => array_map(self::doctorJson(...), $doctors),
        ]);
    }

    /**
     * GET /api/slots?doctor_id=3&week=2026-11-16
     */
    public function slots(Request $request): Response
    {
        $doctorId = self::toInt($request->query('doctor_id')) ?? throw new HttpException(422, 'Doktor seçilmedi.');

        try {
            $doctor = $this->directory->doctor($doctorId);
        } catch (NotFoundException $e) {
            throw new HttpException(404, $e->getMessage());
        }

        // Cron yerine: süresi dolan bekleme listesi teklifleri istek anında işlenir
        $this->waitlist->expireStale();

        $week = self::toDate($request->query('week'), 'Y-m-d') ?? $this->slots->currentWeekStart();
        $monday = $this->slots->weekStart($week);
        $navigation = $this->slots->weekNavigation($monday);
        $grid = $this->slots->weekGrid($doctor, $monday);

        $days = [];
        $available = 0;
        foreach ($grid as $date => $slots) {
            $day = new \DateTimeImmutable($date);
            $available += count(array_filter($slots, static fn (Slot $s): bool => $s->isAvailable()));
            $days[] = [
                'date' => $date,
                'label' => self::DAY_SHORT[(int) $day->format('N')] . ' ' . $day->format('d.m'),
                'day_name' => Schedule::DAY_NAMES[(int) $day->format('N')],
                'slots' => array_map(static fn (Slot $s): array => $s->toArray(), $slots),
            ];
        }

        return Response::json([
            'doctor' => self::doctorJson($doctor),
            'week_start' => $monday->format('Y-m-d'),
            'week_label' => $monday->format('d.m.Y') . ' - ' . $monday->modify('+6 days')->format('d.m.Y'),
            'prev_week' => $navigation['prev']?->format('Y-m-d'),
            'next_week' => $navigation['next']?->format('Y-m-d'),
            'available_count' => $available,
            'days' => $days,
        ]);
    }

    /**
     * @return array{id: int, name: string, department: string, slot_minutes: int, photo_url: ?string}
     */
    private static function doctorJson(Doctor $doctor): array
    {
        return [
            'id' => $doctor->id(),
            'name' => $doctor->displayName(),
            'department' => $doctor->departmentName,
            'slot_minutes' => $doctor->slotMinutes,
            'photo_url' => $doctor->photoPath === null ? null : '/users/' . $doctor->id() . '/photo',
        ];
    }
}
