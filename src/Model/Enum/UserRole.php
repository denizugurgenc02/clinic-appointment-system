<?php

declare(strict_types=1);

namespace App\Model\Enum;

enum UserRole: string
{
    case Patient = 'patient';
    case Doctor = 'doctor';
    case Secretary = 'secretary';
    /** Yerleşik sistem yöneticisi: hiçbir rol/sahiplik kısıtına tabi değildir */
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Patient => 'Hasta',
            self::Doctor => 'Doktor',
            self::Secretary => 'Sekreter',
            self::SuperAdmin => 'Süper Yönetici',
        };
    }

    /**
     * Girişten sonra yönlendirilecek ana sayfa.
     */
    public function homePath(): string
    {
        return match ($this) {
            self::Patient => '/appointments',
            self::Doctor => '/doctor',
            self::Secretary => '/secretary',
            self::SuperAdmin => '/admin',
        };
    }
}
