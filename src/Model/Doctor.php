<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\UserRole;
use DateTimeImmutable;

final class Doctor extends User
{
    public function __construct(
        ?int $id,
        string $name,
        string $email,
        string $passwordHash,
        public int $departmentId,
        public string $title,
        /** Randevu (slot) süresi, dakika (K1) */
        public int $slotMinutes,
        ?string $photoPath = null,
        ?DateTimeImmutable $createdAt = null,
        /** Okuma kolaylığı için doldurulur (JOIN karşılığı); kaydedilmez */
        public string $departmentName = '',
    ) {
        parent::__construct($id, $name, $email, $passwordHash, UserRole::Doctor, $photoPath, $createdAt);
    }

    public function displayName(): string
    {
        return trim($this->title . ' ' . $this->name);
    }
}
