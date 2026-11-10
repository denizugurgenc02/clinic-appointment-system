<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\View\View;
use App\Service\AuthService;
use App\Service\ClinicDirectoryService;

final class HomeController extends AbstractController
{
    public function __construct(
        View $view,
        Session $session,
        AuthService $auth,
        private readonly ClinicDirectoryService $directory,
    ) {
        parent::__construct($view, $session, $auth);
    }

    /**
     * Giriş yapmış kullanıcı rolünün ana sayfasına gider; ziyaretçi tanıtım sayfasını görür.
     */
    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user !== null) {
            return $this->redirect($user->role->homePath());
        }

        $departments = [];
        foreach ($this->directory->departments() as $department) {
            $departments[] = [
                'department' => $department,
                'doctors' => $this->directory->doctors($department->id),
            ];
        }

        return $this->render('home/index', [
            'title' => 'Klinik Randevu Sistemi',
            'departments' => $departments,
        ]);
    }
}
