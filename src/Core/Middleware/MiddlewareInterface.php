<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * İstek controller'a ulaşmadan önce/sonra çalışan ara katman
 * (CSRF, oturum, rol kontrolü). İsteği reddetmek için HttpException fırlatır
 * ya da yanıt döndürür; devam etmek için $next($request) çağırır.
 */
interface MiddlewareInterface
{
    /**
     * @param callable(Request): Response $next
     */
    public function process(Request $request, callable $next): Response;
}
