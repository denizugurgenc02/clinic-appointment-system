<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\Enum\UserRole;

/**
 * Yalnızca süper yönetici (poliklinik ve personel yönetimi).
 */
final class RequireSuperAdmin extends RoleMiddleware
{
    protected function roles(): array
    {
        return [UserRole::SuperAdmin];
    }
}
