<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Exception\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Session\Csrf;
use App\Core\Session\Session;
use PHPUnit\Framework\TestCase;

final class CsrfMiddlewareTest extends TestCase
{
    private Csrf $csrf;
    private CsrfMiddleware $middleware;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->csrf = new Csrf(new Session());
        $this->middleware = new CsrfMiddleware($this->csrf);
    }

    public function testGetRequestPassesWithoutToken(): void
    {
        $response = $this->middleware->process(new Request('GET', '/'), static fn (): Response => Response::html('ok'));

        self::assertSame('ok', $response->body());
    }

    public function testPostWithValidFormTokenPasses(): void
    {
        $request = new Request('POST', '/appointments', body: ['_csrf' => $this->csrf->token()]);

        $response = $this->middleware->process($request, static fn (): Response => Response::html('ok'));

        self::assertSame('ok', $response->body());
    }

    public function testPostWithValidHeaderTokenPasses(): void
    {
        $request = new Request('POST', '/api/x', headers: ['x-csrf-token' => $this->csrf->token()]);

        $response = $this->middleware->process($request, static fn (): Response => Response::html('ok'));

        self::assertSame(200, $response->status());
    }

    public function testPostWithMissingOrWrongTokenIsRejected(): void
    {
        $this->csrf->token();

        foreach ([[], ['_csrf' => 'yanlis']] as $body) {
            try {
                $this->middleware->process(
                    new Request('POST', '/appointments', body: $body),
                    static fn (): Response => Response::html('ok'),
                );
                self::fail('419 bekleniyordu');
            } catch (HttpException $e) {
                self::assertSame(419, $e->statusCode());
            }
        }
    }
}
