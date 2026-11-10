<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Container;
use App\Core\Exception\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\Router\Router;
use LogicException;
use PHPUnit\Framework\TestCase;

final class StubController
{
    public function show(Request $request): Response
    {
        return Response::html('randevu:' . $request->param('id'));
    }

    public function notAResponse(Request $request): string
    {
        return 'hata';
    }
}

/** Middleware çalışma sırasını kaydeder. */
final class CallLog
{
    /** @var list<string> */
    public array $calls = [];
}

final class OuterMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly CallLog $log)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->log->calls[] = 'outer';

        return $next($request);
    }
}

final class InnerMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly CallLog $log)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->log->calls[] = 'inner';

        return $next($request);
    }
}

final class BlockingMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        throw HttpException::forbidden();
    }
}

final class RouterTest extends TestCase
{
    private Container $container;
    private Router $router;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->router = new Router($this->container);
    }

    public function testDispatchesToControllerWithRouteParameter(): void
    {
        $this->router->get('/appointments/{id}', [StubController::class, 'show']);

        $response = $this->router->dispatch(new Request('GET', '/appointments/42?tab=detay'));

        self::assertSame('randevu:42', $response->body());
    }

    public function testTrailingSlashIsIgnored(): void
    {
        $this->router->get('/appointments/{id}', [StubController::class, 'show']);

        self::assertSame('randevu:7', $this->router->dispatch(new Request('GET', '/appointments/7/'))->body());
    }

    public function testUnknownPathThrows404WithoutEchoingThePath(): void
    {
        try {
            $this->router->dispatch(new Request('GET', '/<script>alert(1)</script>'));
            self::fail('404 bekleniyordu');
        } catch (HttpException $e) {
            self::assertSame(404, $e->statusCode());
            self::assertStringNotContainsString('<script>', $e->getMessage());
        }
    }

    public function testWrongMethodThrows405WithAllowHeader(): void
    {
        $this->router->post('/appointments', static fn (): Response => Response::html('ok'));

        try {
            $this->router->dispatch(new Request('GET', '/appointments'));
            self::fail('405 bekleniyordu');
        } catch (HttpException $e) {
            self::assertSame(405, $e->statusCode());
            self::assertSame(['Allow' => 'POST'], $e->headers());
        }
    }

    public function testGlobalMiddlewareRunsBeforeRouteMiddleware(): void
    {
        $log = new CallLog();
        $this->container->instance(CallLog::class, $log);
        $this->router->addGlobalMiddleware(OuterMiddleware::class);
        $this->router->get('/', static function () use ($log): Response {
            $log->calls[] = 'controller';

            return Response::html('ok');
        })->middleware(InnerMiddleware::class);

        $this->router->dispatch(new Request('GET', '/'));

        self::assertSame(['outer', 'inner', 'controller'], $log->calls);
    }

    public function testMiddlewareCanStopTheRequest(): void
    {
        $this->router->get('/secretary', static fn (): Response => Response::html('gizli'))
            ->middleware(BlockingMiddleware::class);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $this->router->dispatch(new Request('GET', '/secretary'));
    }

    public function testHandlerMustReturnResponse(): void
    {
        $this->router->get('/bad', [StubController::class, 'notAResponse']);

        $this->expectException(LogicException::class);

        $this->router->dispatch(new Request('GET', '/bad'));
    }
}
