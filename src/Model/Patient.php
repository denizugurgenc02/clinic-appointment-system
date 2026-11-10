<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\UserRole;
use DateTimeImmutable;

final class Patient extends User
{
    public function __construct(
        ?int $id,
        string $name,
        string $email,
        string $passwordHash,
        public string $phone,
        ?string $photoPath = null,
        ?DateTimeImmutable $createdAt = null,
    ) {
        parent::__construct($id, $name, $email, $passwordHash, UserRole::Patient, $photoPath, $createdAt);
    }
}
