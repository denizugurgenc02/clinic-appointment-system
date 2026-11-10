<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Exception\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Csrf;

/**
 * Durum değiştiren (GET/HEAD dışındaki) tüm isteklerde CSRF jetonunu doğrular.
 * Jeton formlarda "_csrf" alanından, fetch isteklerinde "X-CSRF-Token" başlığından okunur.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), self::SAFE_METHODS, true)) {
            $token = $request->input('_csrf') ?? $request->header('x-csrf-token');

            if (!$this->csrf->isValid($token)) {
                throw HttpException::csrfMismatch();
            }
        }

        return $next($request);
    }
}
