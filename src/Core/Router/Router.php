<?php

declare(strict_types=1);

namespace App\Core\Router;

use App\Core\Container;
use App\Core\Exception\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;
use Closure;
use LogicException;

/**
 * İsteği yol ve yönteme göre ilgili controller metoduna yönlendirir.
 *
 * Akış: global middleware -> rota middleware -> controller.
 * Controller'lar DI konteynerinden üretilir ve her zaman Response döndürür.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var list<class-string<MiddlewareInterface>> */
    private array $globalMiddleware = [];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array{0: class-string, 1: string}|Closure $handler
     */
    public function get(string $pattern, array|Closure $handler): Route
    {
        return $this->add('GET', $pattern, $handler);
    }

    /**
     * @param array{0: class-string, 1: string}|Closure $handler
     */
    public function post(string $pattern, array|Closure $handler): Route
    {
        return $this->add('POST', $pattern, $handler);
    }

    /**
     * @param class-string<MiddlewareInterface> $middleware
     */
    public function addGlobalMiddleware(string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * @return list<Route>
     */
    public function routes(): array
    {
        return $this->routes;
    }

    public function dispatch(Request $request): Response
    {
        $path = $request->path();
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $params = $route->match($path);
            if ($params === null) {
                continue;
            }

            if ($route->method() !== $request->method()) {
                $allowedMethods[] = $route->method();
                continue;
            }

            $middleware = [...$this->globalMiddleware, ...$route->middlewareList()];

            return $this->runPipeline(
                $middleware,
                $request->withRouteParams($params),
                fn (Request $r): Response => $this->callHandler($route, $r),
            );
        }

        if ($allowedMethods !== []) {
            throw HttpException::methodNotAllowed(array_values(array_unique($allowedMethods)));
        }

        throw HttpException::notFound();
    }

    /**
     * @param array{0: class-string, 1: string}|Closure $handler
     */
    private function add(string $method, string $pattern, array|Closure $handler): Route
    {
        $route = new Route($method, $pattern, $handler);
        $this->routes[] = $route;

        return $route;
    }

    /**
     * Middleware'leri soğan katmanları gibi sarar: ilk eklenen en dışta çalışır.
     *
     * @param list<class-string<MiddlewareInterface>> $middleware
     * @param Closure(Request): Response              $core
     */
    private function runPipeline(array $middleware, Request $request, Closure $core): Response
    {
        $next = $core;

        foreach (array_reverse($middleware) as $class) {
            $instance = $this->container->get($class);
            $next = static fn (Request $r): Response => $instance->process($r, $next);
        }

        return $next($request);
    }

    private function callHandler(Route $route, Request $request): Response
    {
        $handler = $route->handler();

        if ($handler instanceof Closure) {
            $response = $handler($request);
        } else {
            [$class, $method] = $handler;
            $controller = $this->container->get($class);

            if (!method_exists($controller, $method)) {
                throw new LogicException(sprintf('%s::%s metodu bulunamadı.', $class, $method));
            }

            $response = $controller->{$method}($request);
        }

        if (!$response instanceof Response) {
            throw new LogicException(sprintf('"%s" rotasının işleyicisi Response döndürmeli.', $route->pattern()));
        }

        return $response;
    }
}
