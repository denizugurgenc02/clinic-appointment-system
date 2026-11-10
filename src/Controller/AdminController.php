<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\View\View;
use App\Service\AuthService;
use App\Service\ClinicAdminService;
use App\Service\ClinicDirectoryService;
use App\Service\Exception\ValidationException;
use DomainException;

/**
 * Süper yönetici paneli: poliklinik ve personel (doktor, sekreter) yönetimi.
 *
 * POST uçları hem HTML formlarından hem de JSON istemcilerinden çağrılabilir:
 * "Accept: application/json" (veya JSON gövde) ile gönderilirse yanıt JSON'dur
 * (başarıda 201 + kayıt, hatada 422 + alan hataları); aksi halde sayfaya yönlendirilir.
 */
final class AdminController extends AbstractController
{
    public function __construct(
        View $view,
        Session $session,
        AuthService $auth,
        private readonly ClinicAdminService $admin,
        private readonly ClinicDirectoryService $directory,
    ) {
        parent::__construct($view, $session, $auth);
    }

    /**
     * GET /admin
     */
    public function index(Request $request): Response
    {
        $doctorCounts = [];
        foreach ($this->directory->doctors() as $doctor) {
            $doctorCounts[$doctor->departmentId] = ($doctorCounts[$doctor->departmentId] ?? 0) + 1;
        }

        return $this->render('admin/index', [
            'title' => 'Yönetim',
            'departments' => $this->directory->departments(),
            'doctorCounts' => $doctorCounts,
            'doctors' => $this->directory->doctors(),
        ]);
    }

    /**
     * GET /api/departments — JSON poliklinik listesi
     */
    public function departments(Request $request): Response
    {
        return Response::json(['departments' => array_map(
            static fn ($d): array => ['id' => $d->id, 'name' => $d->name, 'description' => $d->description],
            $this->directory->departments(),
        )]);
    }

    /**
     * POST /admin/departments
     */
    public function storeDepartment(Request $request): Response
    {
        return $this->attempt($request, function () use ($request): array {
            $department = $this->admin->createDepartment((string) $request->input('name'), (string) $request->input('description'));

            return [
                'message' => '"' . $department->name . '" polikliniği oluşturuldu.',
                'data' => ['id' => $department->id, 'name' => $department->name, 'description' => $department->description],
            ];
        });
    }

    /**
     * POST /admin/departments/{id}
     */
    public function updateDepartment(Request $request): Response
    {
        return $this->attempt($request, function () use ($request): array {
            $department = $this->admin->updateDepartment(
                self::requireInt($request->param('id'), 'id'),
                (string) $request->input('name'),
                (string) $request->input('description'),
            );

            return [
                'message' => 'Poliklinik güncellendi.',
                'data' => ['id' => $department->id, 'name' => $department->name, 'description' => $department->description],
            ];
        }, 200);
    }

    /**
     * POST /admin/doctors
     */
    public function storeDoctor(Request $request): Response
    {
        return $this->attempt($request, function () use ($request): array {
            $doctor = $this->admin->createDoctor(
                (string) $request->input('name'),
                (string) $request->input('email'),
                (string) $request->input('password'),
                self::requireInt($request->input('department_id'), 'department_id'),
                (string) $request->input('title'),
                self::toInt($request->input('slot_minutes')) ?? 0,
            );

            return [
                'message' => $doctor->displayName() . ' hesabı oluşturuldu. Şimdi çalışma takvimini ekleyin.',
                'redirect' => '/secretary/doctors/' . $doctor->id(),
                'data' => ['id' => $doctor->id, 'name' => $doctor->displayName(), 'department_id' => $doctor->departmentId],
            ];
        });
    }

    /**
     * POST /admin/secretaries
     */
    public function storeSecretary(Request $request): Response
    {
        return $this->attempt($request, function () use ($request): array {
            $user = $this->admin->createSecretary(
                (string) $request->input('name'),
                (string) $request->input('email'),
                (string) $request->input('password'),
            );

            return [
                'message' => $user->name . ' sekreter hesabı oluşturuldu.',
                'data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            ];
        });
    }

    /**
     * İşlemi çalıştırır; isteğin türüne göre JSON veya yönlendirme yanıtı üretir.
     *
     * @param callable(): array{message: string, data: array<string, mixed>, redirect?: string} $operation
     */
    private function attempt(Request $request, callable $operation, int $successStatus = 201): Response
    {
        try {
            $result = $operation();
        } catch (DomainException $e) {
            if ($request->wantsJson()) {
                return Response::json([
                    'error' => $e->getMessage(),
                    'errors' => $e instanceof ValidationException ? $e->errors() : (object) [],
                ], 422);
            }

            return $this->failed($request, $e, '/admin');
        }

        if ($request->wantsJson()) {
            return Response::json(['message' => $result['message'], 'data' => $result['data']], $successStatus);
        }

        $this->success($result['message']);

        return $this->redirect($result['redirect'] ?? '/admin');
    }
}
