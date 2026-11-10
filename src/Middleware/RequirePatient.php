<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\Enum\UserRole;

/**
 * Yalnızca hasta.
 */
final class RequirePatient extends RoleMiddleware
{
    protected function roles(): array
    {
        return [UserRole::Patient];
    }
}
