<?php

declare(strict_types=1);

namespace Tests\Support\Stub;

use App\Core\Exception\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;

final class BlockingMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        throw HttpException::forbidden();
    }
}
