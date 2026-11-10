<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\View\View;
use App\Service\AuthService;

/**
 * Oturumdaki kullanıcıyı tüm görünümlere ($currentUser) paylaştırır (menü, ad, fotoğraf).
 */
final class ShareCurrentUser implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly View $view,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->view->share('currentUser', $this->auth->user());

        return $next($request);
    }
}
