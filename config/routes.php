<?php

declare(strict_types=1);

use App\Controller\ApiController;
use App\Controller\AppointmentController;
use App\Controller\AuthController;
use App\Controller\DoctorPanelController;
use App\Controller\HealthController;
use App\Controller\HomeController;
use App\Controller\ProfileController;
use App\Controller\ReportController;
use App\Controller\ScheduleController;
use App\Controller\SecretaryController;
use App\Controller\WaitlistController;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Router\Router;
use App\Middleware\RequireAuthenticated;
use App\Middleware\RequireDoctor;
use App\Middleware\RequirePatient;
use App\Middleware\RequirePatientOrSecretary;
use App\Middleware\RequireSecretary;
use App\Middleware\ShareCurrentUser;

/*
 * URL -> Controller::metot eşlemeleri.
 *
 * Global: CSRF (GET dışı her istek) + oturumdaki kullanıcının görünümlere paylaşılması.
 * Rol kontrolü her rotada ->middleware(Require...) ile yapılır; sahiplik kontrolü serviste.
 */

return static function (Router $router): void {
    $router->addGlobalMiddleware(CsrfMiddleware::class);
    $router->addGlobalMiddleware(ShareCurrentUser::class);

    // --- Herkese açık ---------------------------------------------------------------------
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/register', [AuthController::class, 'showRegister']);
    $router->post('/register', [AuthController::class, 'register']);
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->get('/api/health', [HealthController::class, 'show']);

    // --- AJAX (JSON) ----------------------------------------------------------------------
    $router->get('/api/doctors', [ApiController::class, 'doctors'])->middleware(RequirePatientOrSecretary::class);
    $router->get('/api/slots', [ApiController::class, 'slots'])->middleware(RequirePatientOrSecretary::class);

    // --- Hasta ----------------------------------------------------------------------------
    $router->get('/appointments', [AppointmentController::class, 'index'])->middleware(RequirePatient::class);
    $router->get('/appointments/book', [AppointmentController::class, 'create'])->middleware(RequirePatient::class);
    $router->post('/appointments', [AppointmentController::class, 'store'])->middleware(RequirePatient::class);
    $router->get('/appointments/{id}/reschedule', [AppointmentController::class, 'edit'])->middleware(RequirePatientOrSecretary::class);
    $router->post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule'])->middleware(RequirePatientOrSecretary::class);
    $router->post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])->middleware(RequirePatientOrSecretary::class);

    $router->post('/waitlist', [WaitlistController::class, 'join'])->middleware(RequirePatient::class);
    $router->post('/waitlist/{id}/accept', [WaitlistController::class, 'accept'])->middleware(RequirePatient::class);
    $router->post('/waitlist/{id}/decline', [WaitlistController::class, 'decline'])->middleware(RequirePatient::class);
    $router->post('/waitlist/{id}/leave', [WaitlistController::class, 'leave'])->middleware(RequirePatient::class);

    // --- Doktor ---------------------------------------------------------------------------
    $router->get('/doctor', [DoctorPanelController::class, 'index'])->middleware(RequireDoctor::class);
    $router->post('/doctor/appointments/{id}/status', [DoctorPanelController::class, 'updateStatus'])->middleware(RequireDoctor::class);

    // --- Sekreter -------------------------------------------------------------------------
    $router->get('/secretary', [SecretaryController::class, 'index'])->middleware(RequireSecretary::class);
    $router->get('/secretary/appointments/new', [SecretaryController::class, 'create'])->middleware(RequireSecretary::class);
    $router->post('/secretary/appointments', [SecretaryController::class, 'store'])->middleware(RequireSecretary::class);
    $router->get('/secretary/waitlist', [SecretaryController::class, 'waitlist'])->middleware(RequireSecretary::class);

    $router->get('/secretary/doctors', [ScheduleController::class, 'index'])->middleware(RequireSecretary::class);
    $router->get('/secretary/doctors/{id}', [ScheduleController::class, 'show'])->middleware(RequireSecretary::class);
    $router->post('/secretary/doctors/{id}/schedules', [ScheduleController::class, 'addRow'])->middleware(RequireSecretary::class);
    $router->post('/secretary/schedules/{id}/delete', [ScheduleController::class, 'deleteRow'])->middleware(RequireSecretary::class);
    $router->post('/secretary/doctors/{id}/time-off', [ScheduleController::class, 'addTimeOff'])->middleware(RequireSecretary::class);
    $router->post('/secretary/time-off/{id}/delete', [ScheduleController::class, 'deleteTimeOff'])->middleware(RequireSecretary::class);

    $router->get('/reports/daily', [ReportController::class, 'daily'])->middleware(RequireSecretary::class);

    // --- Profil ---------------------------------------------------------------------------
    $router->get('/profile', [ProfileController::class, 'show'])->middleware(RequireAuthenticated::class);
    $router->post('/profile/photo', [ProfileController::class, 'uploadPhoto'])->middleware(RequireAuthenticated::class);
    $router->get('/users/{id}/photo', [ProfileController::class, 'photo'])->middleware(RequireAuthenticated::class);
};
