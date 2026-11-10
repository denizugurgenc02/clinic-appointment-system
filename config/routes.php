<?php

declare(strict_types=1);

use App\Controller\HealthController;
use App\Controller\HomeController;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Router\Router;

/*
 * URL -> Controller::metot eşlemeleri.
 * Parametreli yol örneği: $router->post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
 * Rol kısıtı Faz 6'da ->middleware(RoleMiddleware...) ile eklenecek.
 */

return static function (Router $router): void {
    // GET dışındaki tüm isteklerde CSRF jetonu zorunlu
    $router->addGlobalMiddleware(CsrfMiddleware::class);

    $router->get('/', [HomeController::class, 'index']);

    // AJAX/JSON altyapısının çalıştığını gösteren basit uç nokta
    $router->get('/api/health', [HealthController::class, 'show']);
};
