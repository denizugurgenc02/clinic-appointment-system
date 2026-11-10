<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\Enum\UserRole;

/**
 * Yalnızca doktor.
 */
final class RequireDoctor extends RoleMiddleware
{
    protected function roles(): array
    {
        return [UserRole::Doctor];
    }
}
