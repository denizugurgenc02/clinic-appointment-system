<?php

declare(strict_types=1);

namespace Tests\Support\Stub;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;

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
