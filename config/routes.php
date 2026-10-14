<?php
/**
 * URL Route Tanımları
 * Hangi URL hangi Controller'ın hangi metoduna gidecek burada belirlenir.
 */

use App\Core\Router\Router;
use App\Patient\PatientController;

return function(Router $router) {
    // Anasayfa isteği geldiğinde PatientController içindeki index metodunu çalıştır
    $router->get('/', [PatientController::class, 'index']);
    $router->get('/patient/home', [PatientController::class, 'index']);
};