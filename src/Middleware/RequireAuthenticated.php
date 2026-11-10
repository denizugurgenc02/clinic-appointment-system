<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\Enum\UserRole;

/**
 * Giriş yapmış herhangi bir kullanıcı.
 */
final class RequireAuthenticated extends RoleMiddleware
{
    protected function roles(): array
    {
        return [UserRole::Patient, UserRole::Doctor, UserRole::Secretary];
    }
}
