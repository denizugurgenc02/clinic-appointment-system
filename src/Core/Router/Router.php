<?php
namespace App\Core\Router;

use App\Core\Container;

class Router 
{
    private array $routes = [];
    private Container $container;

    public function __construct(Container $container) 
    {
        $this->container = $container;
    }

    public function get(string $path, array|callable $callback): void 
    {
        $this->routes['get'][$path] = $callback;
    }

    public function post(string $path, array|callable $callback): void 
    {
        $this->routes['post'][$path] = $callback;
    }

    public function dispatch(Request $request): void 
    {
        $path = $request->getPath();
        $method = $request->getMethod();

        $callback = $this->routes[$method][$path] ?? false;

        if ($callback === false) {
            // Rota bulunamadı (404)
            http_response_code(404);
            echo "<h1>404 - Sayfa Bulunamadı</h1><p>Aradığınız adres ({$path}) sistemde mevcut değil.</p>";
            return;
        }

        // Eğer callback bir dizi [ControllerClass::class, 'method'] ise
        if (is_array($callback)) {
            $controllerClass = $callback[0];
            $methodName = $callback[1];

            // DI Container üzerinden Controller'ı bağımlılıklarıyla birlikte üret
            $controller = $this->container->get($controllerClass);
            
            call_user_func([$controller, $methodName]);
            return;
        }

        // Eğer closure (anonim fonksiyon) ise direkt çalıştır
        call_user_func($callback);
    }
}