<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Clock\ClockInterface;
use App\Core\Clock\SystemClock;
use App\Core\Event\EventDispatcher;
use App\Core\Event\EventListenerInterface;
use App\Core\Exception\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Router\Router;
use App\Core\Session\Csrf;
use App\Core\Session\Session;
use App\Core\View\View;
use Closure;
use DateTimeZone;
use Throwable;
use UnexpectedValueException;

/**
 * Uygulamanın kurulumu (bootstrap) ve tek bir isteğin işlenmesi.
 *
 * public/index.php yalnızca şunu yapar:
 *   (new Application($root))->handle(Request::fromGlobals())->send();
 *
 * Kurulum sırası: config -> çekirdek servisler -> config/services.php ->
 * config/events.php (Observer kayıtları) -> config/routes.php.
 */
final class Application
{
    private readonly Container $container;
    private readonly Router $router;

    public function __construct(private readonly string $basePath)
    {
        $this->container = new Container();
        $this->bootstrap();
        $this->router = $this->container->get(Router::class);
        $this->loadRoutes();
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function handle(Request $request): Response
    {
        try {
            $this->prepareSession();

            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            return $this->renderError($request, $e->statusCode(), $e->getMessage(), null, $e->headers());
        } catch (Throwable $e) {
            $this->log($e);

            return $this->renderError($request, 500, 'Beklenmeyen bir hata oluştu.', $e);
        }
    }

    private function bootstrap(): void
    {
        $config = new Config($this->requireArray('config/app.php'));
        $timezone = new DateTimeZone($config->string('timezone', 'Europe/Istanbul'));
        date_default_timezone_set($timezone->getName());

        $c = $this->container;
        $c->instance(Container::class, $c);
        $c->instance(Config::class, $config);
        $c->instance(ClockInterface::class, new SystemClock($timezone));
        $c->instance(Session::class, new Session());
        $c->instance(View::class, new View($this->path('views')));
        $c->singleton(Csrf::class, static fn (Container $c): Csrf => new Csrf($c->get(Session::class)));
        $c->singleton(EventDispatcher::class, static fn (): EventDispatcher => new EventDispatcher());
        $c->singleton(Router::class, static fn (Container $c): Router => new Router($c));

        $services = $this->requireFile('config/services.php');
        if ($services instanceof Closure) {
            $services($c, $config);
        }

        $this->registerListeners();
    }

    private function registerListeners(): void
    {
        $dispatcher = $this->container->get(EventDispatcher::class);

        foreach ($this->requireArray('config/events.php') as $event => $listeners) {
            if (!class_exists($event) || !is_array($listeners)) {
                throw new UnexpectedValueException('config/events.php geçersiz: [Olay::class => [Dinleyici::class, ...]] bekleniyor.');
            }
            foreach ($listeners as $listenerClass) {
                if (!is_string($listenerClass) || !is_subclass_of($listenerClass, EventListenerInterface::class)) {
                    throw new UnexpectedValueException(sprintf('%s bir EventListenerInterface değil.', var_export($listenerClass, true)));
                }
                $dispatcher->listen($event, $this->container->get($listenerClass));
            }
        }
    }

    private function loadRoutes(): void
    {
        $routes = $this->requireFile('config/routes.php');

        if (!$routes instanceof Closure) {
            throw new UnexpectedValueException('config/routes.php bir fonksiyon (Closure) döndürmeli.');
        }

        $routes($this->router);
    }

    /**
     * Oturumu başlatır ve bir önceki istekten kalan flash mesajlarını,
     * hatalı formda girilen değerleri ve CSRF jetonunu görünümlere paylaştırır.
     */
    private function prepareSession(): void
    {
        $session = $this->container->get(Session::class);
        $session->start();

        $view = $this->container->get(View::class);
        $view->share('flashes', $session->pullFlashes());
        $view->share('old', $session->pullOldInput());
        $view->share('csrfToken', $this->container->get(Csrf::class)->token());
        $view->share('currentUser', null); // Faz 6'da AuthService ile doldurulacak
    }

    /**
     * @param array<string, string> $headers
     */
    private function renderError(Request $request, int $status, string $message, ?Throwable $error, array $headers = []): Response
    {
        $config = $this->container->get(Config::class);
        $debug = $config->bool('debug') && $error !== null;

        if ($request->wantsJson()) {
            $payload = ['error' => $message];
            if ($debug) {
                $payload['exception'] = $error->getMessage();
            }
            $response = Response::json($payload, $status);
        } else {
            try {
                $html = $this->container->get(View::class)->render('errors/error', [
                    'title' => 'Hata ' . $status,
                    'status' => $status,
                    'message' => $message,
                    'debug' => $debug ? $error : null,
                ]);
            } catch (Throwable) {
                $html = '<h1>Hata ' . $status . '</h1><p>' . e($message) . '</p>';
            }
            $response = Response::html($html, $status);
        }

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    private function log(Throwable $e): void
    {
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            date('Y-m-d H:i:s'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        );

        error_log($line, 3, $this->path('storage/logs/app.log'));
    }

    private function path(string $relative): string
    {
        return rtrim($this->basePath, '/\\') . '/' . $relative;
    }

    private function requireFile(string $relative): mixed
    {
        return require $this->path($relative);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireArray(string $relative): array
    {
        $value = $this->requireFile($relative);

        if (!is_array($value)) {
            throw new UnexpectedValueException(sprintf('%s bir dizi döndürmeli.', $relative));
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
