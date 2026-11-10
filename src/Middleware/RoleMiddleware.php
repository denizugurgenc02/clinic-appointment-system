<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exception\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\Session\Session;
use App\Model\Enum\UserRole;
use App\Service\AuthService;

/**
 * Rota seviyesinde oturum ve rol kontrolü (her işlemde yetki kontrolü).
 *
 * - Giriş yapılmamışsa: sayfa isteğinde /login'e yönlendirir, AJAX isteğinde 401 JSON.
 * - Rol uygun değilse: 403. Süper yönetici (hasFullAccess) her zaman geçer.
 *
 * Alt sınıflar yalnızca izin verilen rolleri söyler (RequirePatient, RequireSecretary ...).
 */
abstract class RoleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Session $session,
    ) {
    }

    /**
     * @return list<UserRole>
     */
    abstract protected function roles(): array;

    public function process(Request $request, callable $next): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            if ($request->wantsJson()) {
                throw new HttpException(401, 'Bu işlem için giriş yapmanız gerekiyor.');
            }

            $this->session->flash('warning', 'Devam etmek için lütfen giriş yapın.');

            return Response::redirect('/login');
        }

        // Süper yönetici tüm rotalara erişir
        if (!$user->hasFullAccess() && !in_array($user->role, $this->roles(), true)) {
            throw HttpException::forbidden();
        }

        return $next($request);
    }
}
