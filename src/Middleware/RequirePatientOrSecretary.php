<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\Enum\UserRole;

/**
 * Hasta veya sekreter (ör. randevu iptali, slot ızgarası).
 */
final class RequirePatientOrSecretary extends RoleMiddleware
{
    protected function roles(): array
    {
        return [UserRole::Patient, UserRole::Secretary];
    }
}
