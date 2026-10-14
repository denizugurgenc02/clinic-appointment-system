<?php
session_start();
$config = require __DIR__ . '/../config/app.php';
date_default_timezone_set($config['timezone'] ?? 'Europe/Istanbul');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\Database;
use App\Core\Router\Router;
use App\Core\Router\Request;

// 1. DI Container ve Altyapı
$container = new Container();
$container->bind(Database::class, function() {
    return new Database();
});

// 2. İstek ve Router Başlatma
$request = new Request();
$router = new Router($container);

// 3. Route'ları yükle
$routesLoader = require __DIR__ . '/../config/routes.php';
$routesLoader($router);

// 4. İsteği Dağıt (Dispatch)
try {
    $router->dispatch($request);
} catch (\Throwable $e) {
    http_response_code(500);
    echo "<div style='padding:20px; background:#f8d7da; color:#721c24;'>";
    echo "<h3>Sistem Hatası:</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}