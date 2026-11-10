<?php

declare(strict_types=1);

namespace App\Model\Enum;

enum UserRole: string
{
    case Patient = 'patient';
    case Doctor = 'doctor';
    case Secretary = 'secretary';

    public function label(): string
    {
        return match ($this) {
            self::Patient => 'Hasta',
            self::Doctor => 'Doktor',
            self::Secretary => 'Sekreter',
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
        };
    }
}
