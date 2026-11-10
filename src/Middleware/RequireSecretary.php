<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\Enum\UserRole;

/**
 * Yalnızca sekreter.
 */
final class RequireSecretary extends RoleMiddleware
{
    protected function roles(): array
    {
        return [UserRole::Secretary];
    }
}
